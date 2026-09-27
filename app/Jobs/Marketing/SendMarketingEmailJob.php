<?php

namespace App\Jobs\Marketing;

use Throwable;
use App\Models\MarketingCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\MarketingNotificationMail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\MarketingCampaignRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Marketing\EmailRateLimiter;

/**
 * 单封营销邮件 Job。
 *
 * 流程：占配额（acquire）→ 无配额则延迟释放重试 → 发送时二次校验用户
 * 「平台通知」邮件开关（尊重发送时刻的最新设置）→ 发送成功记录并结算状态。
 *
 * 限流策略（QQ SMTP）：
 *  - 命中 SMTP 限流类错误 → EmailRateLimiter::penalize()（降配额 + 冷却），
 *    收件人保持 queued，Job 延迟释放重试（最多 15 次attempt），随后标记失败；
 *  - 其它异常 → 直接标记 failed（避免拖垮整条发送管线），不再自动重试。
 */
class SendMarketingEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 20;

    /** @var array<int,int> 退避（秒） */
    public array $backoff = [15, 15, 30, 30, 60, 60, 120, 120, 300];

    public function __construct(
        public int $campaignId,
        public int $recipientId,
    ) {
    }

    public function handle(): void
    {
        $campaign = MarketingCampaign::find($this->campaignId);
        $recipient = MarketingCampaignRecipient::find($this->recipientId);

        if (! $campaign || ! $recipient || $campaign->email_enabled !== true) {
            return;
        }

        if (! config('notifications.marketing.enabled', false) || ! config('notifications.marketing.email.enabled', false)) {
            $this->mark($recipient, MarketingCampaignRecipient::EMAIL_SKIPPED, 'channel_disabled');

            return;
        }

        $rateLimiter = app(EmailRateLimiter::class);

        if (! $rateLimiter->acquire()) {
            $attempts = $this->attempts();

            if ($attempts > 15) {
                $this->mark($recipient, MarketingCampaignRecipient::EMAIL_FAILED, 'quota_exhausted');

                return;
            }

            $this->releaseSafely();

            return;
        }

        $user = $recipient->user;

        $to = (string) ($recipient->email ?: ($user->email ?? ''));

        if ($to === '') {
            $this->mark($recipient, MarketingCampaignRecipient::EMAIL_SKIPPED, 'no_email');

            return;
        }

        // 发送时刻二次校验用户「平台通知」（邮件通道）开关。
        // 注意：原始邮箱收件人（无账号）跳过开关校验——管理员显式指定的外部邮箱即明确授权；
        // 有账号但缺失设置行时按默认开启处理（与产品「默认开启」决策一致）。
        $settings = $user ? $user->emailNotificationSettings : null;

        if ($user && $settings && ! $settings->platform_notifications) {
            $this->mark($recipient, MarketingCampaignRecipient::EMAIL_SKIPPED, 'opt_out');

            return;
        }

        $locale = $user ? (string) ($user->language ?: 'en') : 'en';

        try {
            Mail::to($to)
                ->locale($locale)
                ->send(new MarketingNotificationMail(
                    subjectText: $campaign->subject,
                    campaignTitle: $campaign->title,
                    campaignContent: $campaign->content,
                    destinationUrl: $campaign->landing_url,
                    locale: $locale,
                ));

            $rateLimiter->recordSuccess();

            $this->mark($recipient, MarketingCampaignRecipient::EMAIL_SENT, null);

            $campaign->increment('email_sent_count');
        } catch (Throwable $e) {
            Log::error('Marketing email send failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            if ($rateLimiter->isRateLimitError($e)) {
                $rateLimiter->penalize();

                if ($this->attempts() > 15) {
                    $this->mark($recipient, MarketingCampaignRecipient::EMAIL_FAILED, 'rate_limit');

                    return;
                }

                // 保持 queued，冷却后重试
                $this->releaseSafely();

                return;
            }

            $this->mark($recipient, MarketingCampaignRecipient::EMAIL_FAILED, $e->getMessage());
        }
    }

    /**
     * 延迟释放重试。仅在队列执行上下文（worker 弹出）中真正 release；
     * 直接调用（测试/同步执行）时静默返回，收件人保持 queued，由调度侧后续认领推进。
     */
    protected function releaseSafely(): void
    {
        $seconds = (int) config('notifications.marketing.email.release_seconds', 15);

        if ($this->job !== null) {
            $this->release($seconds);
        }
    }

    private function mark(MarketingCampaignRecipient $recipient, string $status, ?string $error): void
    {
        $recipient->forceFill([
            'email_status' => $status,
            'email_error' => $error,
            'sent_at' => $status === MarketingCampaignRecipient::EMAIL_SENT ? now() : $recipient->sent_at,
        ])->save();
    }
}