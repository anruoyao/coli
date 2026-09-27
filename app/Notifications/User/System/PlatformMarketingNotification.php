<?php

namespace App\Notifications\User\System;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Traits\BaseNotification;

/**
 * 平台营销通知（站内通知）。
 *
 * 由营销活动批量触发；仅写数据库 + 实时广播，不叠加邮件通道
 * （营销邮件由 MarketingNotificationMail 批量独立发送，避免重复）。
 * 不参与 DeduplicatedDatabaseChannel 去重：每个活动是独立内容，不应被 24h 去重窗口吞掉。
 * 是否投递由「发送 Job」依据用户 push 通知设置中的 platform_notifications 开关决定。
 */
class PlatformMarketingNotification extends Notification implements ShouldQueue
{
    use Queueable,
        BaseNotification;

    public $notificationType = Notifications::MARKETING_PLATFORM;

    public function __construct(
        public string $campaignTitle,
        public string $campaignContent,
        public ?string $destinationUrl = null,
        public ?string $imageUrl = null,
        public array $style = [],
        public array $posts = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        if (! config('notifications.marketing.in_app_enabled', true)) {
            return [];
        }

        $channels = ['database'];

        if ($this->isBroadcastEnabled()) {
            array_push($channels, 'broadcast');
        }

        return $channels;
    }

    /**
     * App 侧系统推送 payload 格式（title/body 与 App 端 FirebaseNotificationManager 约定一致）。
     * 预留：配置 FIREBASE_SERVER_KEY 后由 MarketingFcmSender 异步派发，未配置时不影响站内通知。
     */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => $this->campaignTitle,
            'body' => Str::limit($this->campaignContent, 90),
        ];
    }

    public function toDatabase(): array
    {
        return $this->getData();
    }

    private function getData(): array
    {
        return [
            'message_group' => 'marketing',
            'message_key' => 'platform_notice',
            'message_params' => ['title' => $this->campaignTitle],
            'entity' => [
                'title' => $this->campaignTitle,
                'content' => $this->campaignContent,
                'destination_url' => $this->destinationUrl,
                'image_url' => $this->imageUrl,
                'style' => $this->style,
                'posts' => $this->posts,
            ],
            'actor' => $this->systemActorData(),
            'metadata' => [
                'is_viewable' => false,
                'marketing' => true,
                'destination_url' => $this->destinationUrl,
                'image_url' => $this->imageUrl,
                'style' => $this->style,
                'posts' => $this->posts,
            ],
        ];
    }

    /**
     * 系统/官方账号 actor：通知中心以平台 Logo 展示（message 由 lang 渲染为「平台通知：标题」）。
     */
    private function systemActorData(): array
    {
        return [
            'id' => 0,
            'name' => config('app.name'),
            'avatar_url' => asset('assets/logos/light.png'),
            'username' => 'system',
            'type' => 'system',
            'verified' => true,
        ];
    }
}