<?php

namespace App\Jobs\MediaMigration;

use App\Models\MediaMigration;
use App\Services\MediaMigration\MediaArchiveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CreateMediaArchiveJob implements ShouldQueue
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
        // 防并发锁：TTL 覆盖整个任务时长（含 $timeout 3 小时），
        // 防止队列 retry_after 重复投递导致两个进程同时写同一个 zip。
        $lock = Cache::lock("media-migration:job:{$this->migrationId}", 60 * 60 * 4);

        if (! $lock->get()) {
            return;
        }

        try {
            $migration = MediaMigration::find($this->migrationId);

            if (! $migration || ! $migration->status->isRunning()) {
                return;
            }

            $archiveService->createArchive($migration);
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
