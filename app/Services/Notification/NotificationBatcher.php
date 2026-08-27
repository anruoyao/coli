<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\NotificationBatch;
use App\Models\Notification;
use App\Notifications\User\System\UnreadCountRefreshNotification;

/**
 * 通知聚合调度器（Digest 数据源）。
 *
 * add/remove   : 维护 notification_batches 聚合缓冲表（同源幂等合并，取消即删除）
 * revokeSocial : 撤销场景统一入口 —— 删除聚合行 + 撤回 App 未读通知 + 实时刷新未读数
 */
class NotificationBatcher
{
    /**
     * 记录一个聚合互动事件。
     * 同一接收者+操作者+实体+类型 重复触发时仅更新 meta（不重置窗口计时起点 source_time）。
     */
    public static function add(int $notifiableId, int $actorId, string $entityType, int $entityId, string $type, array $meta = []): void
    {
        if (! config('notifications.digest.enabled', true) || $notifiableId === $actorId) {
            return;
        }

        NotificationBatch::updateOrCreate(
            [
                'notifiable_id' => $notifiableId,
                'actor_id' => $actorId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'type' => $type,
            ],
            [
                // 显式使用应用时区（UTC），避免依赖 MySQL 默认值导致与 PHP 时区偏移
                'source_time' => now(),
                'meta' => $meta ?: null,
            ]
        );
    }

    /** 移除一条聚合互动事件（取消点赞/删除评论/取关）。 */
    public static function remove(int $notifiableId, int $actorId, string $entityType, int $entityId, string $type): void
    {
        NotificationBatch::query()
            ->where('notifiable_id', $notifiableId)
            ->where('actor_id', $actorId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('type', $type)
            ->delete();
    }

    /**
     * 撤销一组社交互动（如取消点赞/删除评论/取关）：
     * 1. 删除聚合缓冲行（该互动不再进入 Digest 邮件）
     * 2. 删除对应的 App 未读通知
     * 3. 实时刷新接收者未读数（宽依赖 WebSocket 的 main.notification 事件）
     */
    public static function revokeSocial(User $notifiable, ?int $actorId, string $entityType, ?int $entityId, array $types): void
    {
        if (empty($types)) {
            return;
        }

        // 1. 聚合缓冲
        NotificationBatch::query()
            ->where('notifiable_id', $notifiable->id)
            ->when($actorId !== null, fn ($q) => $q->where('actor_id', $actorId))
            ->when($entityId !== null, fn ($q) => $q->where('entity_id', $entityId))
            ->whereIn('type', $types)
            ->delete();

        // 2. App 未读通知
        $query = Notification::query()
            ->where('notifiable_id', $notifiable->id)
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->whereNull('read_at')
            ->whereIn('type', $types);

        if ($actorId !== null) {
            $query->whereRaw('JSON_EXTRACT(data, "$.actor.id") = ?', [$actorId]);
        }

        if ($entityId !== null) {
            $query->whereRaw('JSON_EXTRACT(data, "$.entity.id") = ?', [$entityId]);
        }

        $query->delete();

        // 3. 实时刷新未读数
        try {
            $notifiable->notify(new UnreadCountRefreshNotification());
        } catch (\Throwable $e) {
            // 广播失败不影响主流程
        }
    }
}