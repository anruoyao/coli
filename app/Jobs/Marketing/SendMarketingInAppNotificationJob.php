<?php

namespace App\Jobs\Marketing;

use Throwable;
use App\Models\MarketingCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\MarketingCampaignRecipient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Marketing\MarketingFcmSender;
use App\Notifications\User\System\PlatformMarketingNotification;

/**
 * 站内通知 Job（含预留的 App 系统推送）。
 *
 * 站内通知不设高频限流（写库 + 实时广播即可承载常规流量），仅按每 tick 上限
 * （in_app_per_tick）粗粒度削峰。发送时校验用户「平台通知」推送开关，尊重用户选择退出。
 * FCM 系统级推送默认关闭（受 MARKETING_FCM_ENABLED 与 FIREBASE_SERVER_KEY 双重约束）。
 */
class SendMarketingInAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $campaignId,
        public int $recipientId,
        public array $posts = [],
    ) {
    }

    public function handle(): void
    {
        $campaign = MarketingCampaign::find($this->campaignId);
        $recipient = MarketingCampaignRecipient::find($this->recipientId);

        if (! $campaign || ! $recipient || $campaign->in_app_enabled !== true) {
            return;
        }

        if (! config('notifications.marketing.enabled', false) || ! config('notifications.marketing.in_app_enabled', true)) {
            $this->mark($recipient, MarketingCampaignRecipient::INAP_SKIPPED, 'channel_disabled');

            return;
        }

        $user = $recipient->user;

        if (! $user) {
            $this->mark($recipient, MarketingCampaignRecipient::INAP_SKIPPED, 'no_user');

            return;
        }

        // 发送时刻校验用户「平台通知」推送开关。
        // 缺失设置行时按默认开启处理（与产品「默认开启」决策一致）。
        $settings = $user->pushNotificationSettings;

        if ($settings && ! $settings->platform_notifications) {
            $this->mark($recipient, MarketingCampaignRecipient::INAP_SKIPPED, 'opt_out');

            return;
        }

        try {
            $user->notifyNow(new PlatformMarketingNotification(
                campaignTitle: $campaign->title,
                campaignContent: $campaign->content,
                destinationUrl: $campaign->landing_url,
                imageUrl: $campaign->image_url,
                style: [
                    'title_size' => $campaign->title_size,
                    'title_weight' => $campaign->title_weight,
                ],
                posts: $this->posts ?: app(\App\Services\Marketing\CampaignService::class)->buildPostSnapshots($campaign->post_ids),
            ));

            // App 系统级推送（预留；内部按配置与凭据自行决定是否发送）
            app(MarketingFcmSender::class)->notifyCampaign($campaign->title, $campaign->content, $campaign->landing_url);

            $this->mark($recipient, MarketingCampaignRecipient::INAP_SENT, null);

            $campaign->increment('in_app_sent_count');
        } catch (Throwable $e) {
            Log::error('Marketing in-app notification failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            $this->mark($recipient, MarketingCampaignRecipient::INAP_FAILED, $e->getMessage());
        }
    }

    private function mark(MarketingCampaignRecipient $recipient, string $status, ?string $error): void
    {
        $recipient->forceFill([
            'in_app_status' => $status,
            'in_app_error' => $error,
        ])->save();
    }
}