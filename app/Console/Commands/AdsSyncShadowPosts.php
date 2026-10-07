<?php

namespace App\Console\Commands;

use App\Models\Ad;
use Illuminate\Console\Command;
use App\Services\Ad\AdPostSyncService;

/**
 * 原生广告存量同步：为已有广告补建/对齐影子帖。
 *
 * 一次性部署命令（php artisan ads:sync-shadow-posts）：
 * 遍历全部非草稿广告，按当前 status/approval 生成或修正影子帖状态。
 * 幂等，可重复执行。
 */
class AdsSyncShadowPosts extends Command
{
    protected $signature = 'ads:sync-shadow-posts';

    protected $description = '为存量广告补建/对齐影子帖（原生广告信息流注入）';

    public function handle(): int
    {
        $syncService = app(AdPostSyncService::class);

        $count = 0;

        Ad::query()->excludeDraft()->chunkById(100, function ($ads) use ($syncService, &$count) {
            foreach ($ads as $adData) {
                $syncService->sync($adData);
                $count++;
            }
        });

        $this->info("Synced shadow posts for {$count} ad(s).");

        return self::SUCCESS;
    }
}
