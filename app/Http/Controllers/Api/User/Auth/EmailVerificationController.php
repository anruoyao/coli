<?php
/*
|--------------------------------------------------------------------------
| App 注册邮箱验证码 API（为 Chatter Flutter App 对接新增）
|--------------------------------------------------------------------------
| 公共接口（无需登录），全部走 X-App-Key 准入 + 命名限流：
|  - GET  /api/auth/config             客户端启动注册前检测功能开关
|  - POST /api/auth/email-code/send    发送注册验证码
|  - POST /api/auth/email-code/resend  重新发送验证码
| 只增不改，不影响网页端注册流程。
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\User\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\RegistrationVerificationService;
use App\Services\Blacklist\BlacklistService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class EmailVerificationController extends Controller
{
    use SupportsApiResponses;

    public function __construct(private readonly RegistrationVerificationService $codes)
    {
    }

    /**
     * 注册相关功能开关与验证码参数（客户端据此决定是否展示验证码页）。
     */
    public function config()
    {
        return $this->responseSuccess([
            'data' => [
                'registration_enabled' => (bool) config('features.registration.enabled'),
                'email_verification_enabled' => (bool) config('features.reg_verification.enabled'),
                'verification_type' => (string) config('auth_settings.reg_verification_type', 'email'),
                'resend_cooldown_seconds' => $this->codes->resendCooldownSeconds(),
                'code_expires_seconds' => $this->codes->expiresInSeconds(),
                'password_min' => (int) config('user.validation.password.min', 8),
            ],
        ]);
    }

    /**
     * 发送注册验证码。
     */
    public function sendCode(Request $request)
    {
        return $this->issueCode($request);
    }

    /**
     * 重新发送注册验证码（与发送共用冷却/限流逻辑）。
     */
    public function resendCode(Request $request)
    {
        return $this->issueCode($request);
    }

    private function issueCode(Request $request)
    {
        // 注册开关
        if (! config('features.registration.enabled')) {
            return $this->responseError([
                'message' => __('auth.registration_disabled'),
            ], Response::HTTP_FORBIDDEN);
        }

        // 后台未开启邮箱验证时，App 不应调用此接口
        if (! config('features.reg_verification.enabled')) {
            return $this->responseError([
                'message' => __('auth.verification_disabled'),
            ], Response::HTTP_FORBIDDEN);
        }

        $email = (string) $request->get('email', '');

        $validator = Validator::make(['email' => $email], [
            'email' => ['bail', 'required', 'string', 'email', 'max:120', Rule::unique('users', 'email')],
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        // 邮箱黑名单 / 一次性邮箱域名（与注册接口一致）
        $blacklistService = app(BlacklistService::class);
        if ($blacklistService->isEmailBlacklisted($email)) {
            return $this->responseError([
                'message' => __('auth.email_blocked'),
                'errors' => ['email' => [__('auth.email_blocked')]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $emailDomain = mb_strtolower(mb_substr($email, mb_strpos($email, '@') + 1));
        if (in_array($emailDomain, config('security.disposable_email_domains', []), true)) {
            return $this->responseError([
                'message' => __('auth.email_blocked'),
                'errors' => ['email' => [__('auth.email_blocked')]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 重发冷却（邮箱维度，独立于 IP 限流，防短信/邮件轰炸）
        $timeLeft = $this->codes->cooldownRemaining($email);
        if ($timeLeft > 0) {
            return $this->responseError([
                'message' => __('auth.verification_code_resent_wait', ['seconds' => $timeLeft]),
                'data' => ['time_left' => $timeLeft],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $this->codes->issue($email);
        } catch (Throwable $e) {
            report($e);

            return $this->responseError([
                'message' => __('auth.email_send_failed'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->responseSuccess([
            'data' => [
                'email' => $email,
                'code_expires_seconds' => $this->codes->expiresInSeconds(),
                'resend_cooldown_seconds' => $this->codes->resendCooldownSeconds(),
            ],
        ]);
    }
}
