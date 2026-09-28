<?php
/*
|--------------------------------------------------------------------------
| App 注册邮箱验证码服务（为 Chatter Flutter App 对接新增）
|--------------------------------------------------------------------------
| 职责：
|  - 生成 6 位数字验证码并持久化到 email_confirmations（复用表，纯增量列）
|  - 重发冷却控制（防邮箱轰炸）
|  - 注册时校验验证码（有效期 / 错误次数 / 一次性消费）
| 网页端 token 链接确认流程不受影响。
|--------------------------------------------------------------------------
*/

namespace App\Services\Auth;

use App\Mail\User\Settings\ConfirmationCodeMail;
use App\Models\EmailConfirmation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class RegistrationVerificationService
{
    public const STATUS_OK = 'ok';
    public const STATUS_NOT_FOUND = 'not_found';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_TOO_MANY_ATTEMPTS = 'too_many_attempts';

    /**
     * 验证码有效期（秒）。
     */
    public function expiresInSeconds(): int
    {
        return (int) config('security.auth.verification_code_expires_minutes', 10) * 60;
    }

    /**
     * 重发冷却（秒）。
     */
    public function resendCooldownSeconds(): int
    {
        return (int) config('security.auth.verification_code_resend_cooldown', 60);
    }

    /**
     * 单个验证码允许的最大错误尝试次数。
     */
    public function maxAttempts(): int
    {
        return (int) config('security.auth.verification_code_max_attempts', 5);
    }

    /**
     * 距下次允许发码的剩余冷却秒数（0 表示可立即发送）。
     */
    public function cooldownRemaining(string $email): int
    {
        $last = EmailConfirmation::where('email', $email)
            ->whereNotNull('code')
            ->latest('last_sent_at')
            ->value('last_sent_at');

        if (! $last) {
            return 0;
        }

        $elapsed = now()->getTimestamp() - $last->getTimestamp();

        return max(0, $this->resendCooldownSeconds() - $elapsed);
    }

    /**
     * 生成并发送注册验证码。
     * 同一邮箱只保留最新一条待验证记录；邮件发送失败时回滚记录并抛出异常。
     */
    public function issue(string $email): void
    {
        // 清理该邮箱历史记录（已消费/过期的旧码一并删除，保证「一邮箱一有效码」）
        EmailConfirmation::where('email', $email)->delete();

        $code = $this->generateUniqueCode();
        $now = now();

        $confirmation = EmailConfirmation::create([
            'email' => $email,
            // token 列为 NOT NULL（网页链接流程使用），这里放占位 UUID
            'token' => (string) Str::uuid(),
            'code' => $code,
            'attempts' => 0,
            'expires_at' => $now->copy()->addSeconds($this->expiresInSeconds()),
            'last_sent_at' => $now,
        ]);

        try {
            // 复用设置页 6 位验证码邮件模板；queue 保证不阻塞 API 响应
            // （线上走 Horizon，测试环境 sync 驱动立即发送）
            Mail::to($email)->queue(new ConfirmationCodeMail([
                'title' => __('auth.hi_there'),
                'code' => $code,
                'subTitle' => __('email.code.registration.sub_title', ['app_name' => config('app.name')]),
                'description' => __('email.code.registration.description', [
                    'minutes' => (int) config('security.auth.verification_code_expires_minutes', 10),
                ]),
                'ignoreEmail' => __('email.code.registration.ignore_email'),
            ]));
        } catch (Throwable $e) {
            // 发信失败：删除待验证记录，避免用户被冷却卡死无法立即重试
            $confirmation->delete();

            throw $e;
        }
    }

    /**
     * 校验并消费验证码。
     *
     * @return string 见本类 STATUS_* 常量
     */
    public function verifyAndConsume(string $email, string $code): string
    {
        $confirmation = EmailConfirmation::where('email', $email)
            ->whereNotNull('code')
            ->latest('id')
            ->first();

        if (! $confirmation) {
            return self::STATUS_NOT_FOUND;
        }

        if ($confirmation->expires_at === null || $confirmation->expires_at->isPast()) {
            return self::STATUS_EXPIRED;
        }

        if ((int) $confirmation->attempts >= $this->maxAttempts()) {
            return self::STATUS_TOO_MANY_ATTEMPTS;
        }

        if (! hash_equals((string) $confirmation->code, $code)) {
            $confirmation->increment('attempts');

            if ((int) $confirmation->refresh()->attempts >= $this->maxAttempts()) {
                // 尝试次数耗尽：作废该码，强制重新获取
                $confirmation->delete();

                return self::STATUS_TOO_MANY_ATTEMPTS;
            }

            return self::STATUS_INVALID;
        }

        // 验证通过：一次性消费
        EmailConfirmation::where('email', $email)->delete();

        return self::STATUS_OK;
    }

    /**
     * 生成在未过期记录中不冲突的 6 位数字验证码。
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
            $exists = EmailConfirmation::where('code', $code)
                ->where('expires_at', '>', now())
                ->exists();
        } while ($exists);

        return $code;
    }
}
