<?php

namespace App\Jobs\System;

use App\Services\System\DatabaseRestoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class RestoreDatabaseBackup implements ShouldQueue
{
    use Queueable;

    public $timeout = 60 * 60 * 3;

    public $tries = 1;

    public function __construct(public string $backupName) {}

    public function handle(DatabaseRestoreService $service): void
    {
        $lock = Cache::lock('system:database:operation', 60 * 60 * 4);

        if (! $lock->get()) {
            return;
        }

        $dumpPath = null;

        try {
            if (cache()->has('system:backup:processing')) {
                throw new \RuntimeException('A database backup is still running.');
            }

            $relative = $service->candidatePath($this->backupName);
            $details = $service->validateArchive($relative);
            $service->createEmergencyBackup();
            $dumpPath = $service->extractDump($relative, $details['dump_entry']);
            if (filesize($dumpPath) !== $details['dump_size'] || hash_file('sha256', $dumpPath) !== $details['dump_sha256']) {
                throw new \RuntimeException('Database dump integrity check failed before restore.');
            }
            $service->import($dumpPath);

            DB::purge(config('database.default'));
            DB::connection()->getPdo();
            cache()->put('system:database:restore:last', [
                'status' => 'completed',
                'backup' => $this->backupName,
                'finished_at' => now()->toIso8601String(),
            ], now()->addDay());
        } catch (Throwable $exception) {
            cache()->put('system:database:restore:last', [
                'status' => 'failed',
                'backup' => $this->backupName,
                'error' => $exception->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ], now()->addDay());
            throw $exception;
        } finally {
            if ($dumpPath) {
                @unlink($dumpPath);
            }
            $lock->release();
        }
    }
}
