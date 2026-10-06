<?php

namespace App\Jobs\MediaMigration;

use App\Models\MediaMigration;
use App\Services\MediaMigration\MediaArchiveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ExtractMediaArchiveJob implements ShouldQueue
{
    use Queueable;

    public $timeout = (60 * 60 * 3); // 3 hours

    public $tries = 1;

    public function __construct(public int $migrationId)
    {
        //
    }

    public function handle(MediaArchiveService $archiveService): void
    {
        // 防并发锁：TTL 覆盖整个任务时长（含 $timeout 3 小时）
        $lock = Cache::lock("media-migration:job:{$this->migrationId}", 60 * 60 * 4);

        if (! $lock->get()) {
            return;
        }

        try {
            $migration = MediaMigration::find($this->migrationId);

            if (! $migration || ! $migration->status->isRunning()) {
                return;
            }

            // 解压目标磁盘：解压任务的 source_disk 存放目标磁盘名
            $archiveService->extractArchive($migration, $migration->source_disk);
        } catch (Throwable $th) {
            $migration = MediaMigration::find($this->migrationId);

            if ($migration && $migration->status->isRunning()) {
                $migration->status = \App\Enums\Media\MediaMigrationStatus::FAILED;
                $migration->error = $th->getMessage();
                $migration->finished_at = now();
                $migration->save();
            }
        } finally {
            $lock->release();
        }
    }
}
