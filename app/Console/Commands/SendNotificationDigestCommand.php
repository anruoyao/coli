<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Command;
use App\Models\NotificationBatch;
use App\Jobs\Notification\SendNotificationDigestMailJob;

/**
 * 扫描到期的邮编智能聚合批次，错峰派发 Digest 邮件任务。
 *
 * 每分钟执行一次（cron: schedule:run）。每轮最多认领 batch_per_tick 个用户，
 * 且每个任务附带随机延迟（spread_seconds 内）打散发送时刻，避免整点大量投递
 * 触发 QQ/SMTP 官方频率限制。任务执行成功后删除对应批次；执行失败则认领标记
 * 超时后自动重新认领（最多重复 claim_ttl_minutes 分钟）。
 */
class SendNotificationDigestCommand extends Command
{
    protected $signature = 'notification:send-digest';

    protected $description = '扫描到期的通知聚合批次，错峰派发 Digest 邮件任务';

    public function handle(): int
    {
        if (! config('notifications.digest.enabled', true)) {
            return self::SUCCESS;
        }

        $windowMinutes = (int) config('notifications.digest.window_minutes', 60);
        $limit = (int) config('notifications.digest.batch_per_tick', 20);
        $spreadSeconds = (int) config('notifications.digest.spread_seconds', 300);
        $claimTtlMinutes = (int) config('notifications.digest.claim_ttl_minutes', 15);
        $types = (array) config('notifications.digest.types', []);

        if (empty($types)) {
            return self::SUCCESS;
        }

        $windowEnd = now();
        $dueBoundary = $windowEnd->copy()->subMinutes($windowMinutes);

        // 1) 找出「至少一条批次已到期」的接收者（source_time 超过窗口），随机取一部分用户（错峰削峰）
        $dueUserIds = NotificationBatch::query()
            ->whereIn('type', $types)
            ->where('source_time', '<=', $dueBoundary)
            ->distinct()
            ->pluck('notifiable_id');

        // 2) 打包这些用户当前【全部】待发批次（含窗口内尚未到期的新增批次），
        //    保证同一 3h 窗口内同一接收者的所有互动合并为一封邮件，
        //    避免 A/B 分别在窗口内不同时刻触发却各自单独到期、拆成两封。
        //    直接传递批次 ID 给 Job，由 Job 精确处理。
        $idsByUser = NotificationBatch::query()
            ->whereIn('notifiable_id', $dueUserIds)
            ->get(['id', 'notifiable_id'])
            ->groupBy('notifiable_id')
            ->map(fn ($group) => $group->pluck('id')->all());

        $candidates = $idsByUser
            ->keys()
            ->shuffle()
            ->take($limit)
            ->all();

        $dispatched = 0;

        foreach ($candidates as $userId) {
            $claimKey = "ntf_digest_claim:{$userId}:{$windowEnd->timestamp}";

            if (! Cache::add($claimKey, true, now()->addMinutes($claimTtlMinutes))) {
                continue;
            }

            SendNotificationDigestMailJob::dispatch($userId, $idsByUser[$userId])
                ->delay(now()->addSeconds(rand(0, $spreadSeconds)));

            $dispatched++;
        }

        if ($dispatched > 0) {
            $this->info("Dispatched {$dispatched} digest mail job(s).");
        }

        return self::SUCCESS;
    }
}