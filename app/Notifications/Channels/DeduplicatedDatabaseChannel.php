<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use App\Notifications\Contracts\DeduplicatableNotification;

/**
 * 带去重的数据库通知渠道。
 *
 * 仅对实现了 DeduplicatableNotification 的通知生效：当接收者名下已存在
 * 「同类型 + 同操作者 + 同实体」且处于未读状态（窗口内）的通知时，不新增记录，
 * 只刷新旧记录的 data 与 created_at（浮到列表顶部），避免互动轰炸通知页。
 */
class DeduplicatedDatabaseChannel extends DatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        $payload = $this->buildPayload($notifiable, $notification);

        if (config('notifications.deduplication.enabled', true)
            && $notification instanceof DeduplicatableNotification) {
            $existing = $this->findExistingUnread($notifiable, $payload);

            if ($existing) {
                $existing->forceFill([
                    'data' => $payload['data'],
                    'created_at' => now(),
                ])->save();

                return $existing;
            }
        }

        return parent::send($notifiable, $notification);
    }

    protected function findExistingUnread($notifiable, array $payload)
    {
        $data = $payload['data'];

        $actorId = $data['actor']['id'] ?? null;
        $entityId = $data['entity']['id'] ?? null;

        if ($actorId === null && $entityId === null) {
            return null;
        }

        $windowMinutes = (int) config('notifications.deduplication.unread_window_minutes', 1440);

        $query = $notifiable->notifications()
            ->where('type', $payload['type'])
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subMinutes($windowMinutes));

        $existing = $query->latest('created_at')->limit(50)->get();

        if ($actorId !== null) {
            $existing = $existing->filter(fn ($n) => ($n->data['actor']['id'] ?? null) == $actorId);
        }

        if ($entityId !== null) {
            $existing = $existing->filter(fn ($n) => ($n->data['entity']['id'] ?? null) == $entityId);
        }

        return $existing->first();
    }
}