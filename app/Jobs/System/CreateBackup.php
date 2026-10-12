<?php

namespace App\Jobs\System;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class CreateBackup implements ShouldQueue
{
    use Queueable;

    public $timeout = (60 * 180); // 3 hours

    public function handle(): void
    {
        $lock = Cache::lock('system:database:operation', 60 * 60 * 4);

        if (! $lock->get()) {
            cache()->forget('system:backup:processing');
            $this->fail('Another database operation is already running.');

            return;
        }

        try {
            $exitCode = Artisan::call('backup:run');
            if ($exitCode !== 0) {
                $this->fail('Database backup command failed.');
            }
        } catch (Exception $th) {
            $this->fail($th->getMessage());
        } finally {
            cache()->forget('system:backup:processing');
            $lock->release();
        }
    }
}
