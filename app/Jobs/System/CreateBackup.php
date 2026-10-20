<?php

namespace App\Jobs\System;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CreateBackup implements ShouldQueue
{
    use Queueable;

    public $timeout = (60 * 180); // 3 hours

    public function handle(): void
    {
        $lock = Cache::lock('system:database:operation', 60 * 60 * 4);

        if (! $lock->get()) {
            cache()->forget('system:backup:processing');
            cache()->forever('system:backup:last', [
                'status' => 'failed',
                'error' => 'Another database operation is already running.',
                'finished_at' => now()->toIso8601String(),
            ]);
            $this->fail('Another database operation is already running.');

            return;
        }

        try {
            $exitCode = Artisan::call('backup:run');
            if ($exitCode !== 0) {
                throw new \RuntimeException('Database backup command failed.');
            }

            cache()->forever('system:backup:last', [
                'status' => 'completed',
                'finished_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            cache()->forever('system:backup:last', [
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ]);
            $this->fail($exception->getMessage());
        } finally {
            cache()->forget('system:backup:processing');
            $lock->release();
        }
    }
}
