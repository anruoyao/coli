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
        // 防并发锁（与手动重试/重复派发互斥）
        $lock = Cache::lock("media-migration:job:{$this->migrationId}", 300);

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
