<?php

namespace App\Notifications\User\System;

use App\Support\Num;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * 仅用于通知用户「未读数/通知列表已变化」的空广播（撤销通知、聚合清理等场景）。
 *
 * 仅走 broadcast 渠道（同步发送，不进队列）：
 * - broadcastType 为 main.notification，Web/App 端收到事件后会重新拉取通知列表与未读数。
 */
class UnreadCountRefreshNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['broadcast'];
    }

    public function broadcastType(): string
    {
        return 'main.notification';
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $count = $notifiable->unreadNotifications()->count();

        return new BroadcastMessage([
            'data' => [
                'formatted' => Num::abbreviate($count),
                'raw' => $count,
            ],
        ]);
    }
}