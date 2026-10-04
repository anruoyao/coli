<?php

namespace App\Console\Commands\System;

use App\Database\Configs\Table;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 裁剪 api_throttle_events（限流监控事件），按保留天数删除过期记录。
 * 调度见 bootstrap/app.php withSchedule（dailyAt 03:00）。
 */
class PruneApiThrottleEvents extends Command
{
    protected $signature = 'colibri:prune-throttle-events {--days= : 保留天数（默认读 security.throttle_events.retention_days）}';

    protected $description = 'Prune expired API throttle events (api_throttle_events).';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('security.throttle_events.retention_days', 7));

        if ($days <= 0) {
            $this->info('Retention days <= 0, skip pruning.');

            return self::SUCCESS;
        }

        $deleted = DB::table(Table::API_THROTTLE_EVENTS)
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} throttle events older than {$days} days.");

        return self::SUCCESS;
    }
}
