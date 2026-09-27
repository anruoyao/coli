<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * App 系统级推送（FCM）— 预留实现。
 *
 * 载荷格式：data.title / data.body / data.landing_url / data.type，与 App 端
 * FirebaseNotificationManager（showNotification 读取 data['title']/['body']）
 * 约定一致。发送为「主题订阅」模式（与客户端 subscribeToTopic('chatter') 对应）。
 *
 * 仅当 FIREBASE_SERVER_KEY 已配置 且 营销配置开启 MARKETING_FCM_ENABLED 时真正发送；
 * 否则静默跳过（站内通知不受影响）。注意主题推送无法做按用户退订过滤，
 * 因此默认关闭，仅在确认全量触达需求时由管理员打开。
 */
class MarketingFcmSender
{
    public function isEnabled(): bool
    {
        return (bool) config('notifications.marketing.fcm_enabled', false)
            && filled(config('services.fcm.server_key'));
    }

    /**
     * 向全局主题下发（App 端 channel subscription）。
     *
     * @param array<string,string> $payload data 载荷
     */
    public function sendToTopic(array $payload): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        try {
            Http::timeout(10)
                ->withToken((string) config('services.fcm.server_key'), 'key')
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => '/topics/'.config('services.fcm.topic', 'chatter'),
                    'data' => $payload,
                    'priority' => 'high',
                ]);
        } catch (\Throwable $e) {
            Log::error('MarketingFcmSender send failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 由站内通知 Job 调用：按设计的 App 推送格式组装载荷。
     */
    public function notifyCampaign(string $title, string $content, ?string $landingUrl = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->sendToTopic([
            'title' => $title,
            'body' => mb_substr($content, 0, 90),
            'landing_url' => $landingUrl ?? '',
            'type' => 'marketing',
        ]);
    }
}