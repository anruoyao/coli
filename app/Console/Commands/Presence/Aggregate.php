<?php

namespace App\Console\Commands\Presence;

use Illuminate\Console\Command;
use App\Models\PresenceSnapshot;
use App\Services\User\PresenceService;

/**
 * 在线量小时快照聚合（P1 数据分析底座）。
 *
 * 每小时整点后 5 分钟执行一次（schedule:presence:aggregate hourlyAt(5)）：
 * 取当前时刻在线会话数（分平台，来源 Redis 计数 / DB 兜底），写入当小时桶。
 * window_start 唯一 → 同桶重复执行为幂等覆盖（手工补跑安全）。
 */
class Aggregate extends Command
{
    protected $signature = 'presence:aggregate';

    protected $description = '聚合并记录当前小时的在线上限量（支撑在线趋势分析）';

    public function handle(): int
    {
        $windowStart = now()->startOfHour();

        $counts = app(PresenceService::class)->onlineCounts();

        $snapshot = PresenceSnapshot::query()->firstOrNew(['window_start' => $windowStart]);
        $snapshot->fill([
            'total_count'   => (int) $counts['total'],
            'web_count'     => (int) $counts['web'],
            'android_count' => (int) $counts['android'],
            'ios_count'     => (int) $counts['ios'],
        ]);
        $snapshot->save();

        $this->info(sprintf(
            '[%s] snapshot saved: total=%d web=%d android=%d ios=%d',
            $windowStart->format('Y-m-d H:00'),
            $snapshot->total_count,
            $snapshot->web_count,
            $snapshot->android_count,
            $snapshot->ios_count
        ));

        return self::SUCCESS;
    }
}