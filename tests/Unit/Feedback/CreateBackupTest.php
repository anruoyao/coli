<?php

namespace Tests\Unit\Feedback;

use App\Jobs\System\CreateBackup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class CreateBackupTest extends TestCase
{
    public function test_failed_backup_is_saved_for_the_admin_panel(): void
    {
        Cache::forever('system:backup:processing', true);
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run')
            ->andThrow(new RuntimeException('ZipArchive permission denied.'));

        (new CreateBackup)->handle();

        $this->assertFalse(Cache::has('system:backup:processing'));
        $this->assertSame([
            'status' => 'failed',
            'error' => 'ZipArchive permission denied.',
        ], [
            'status' => Cache::get('system:backup:last')['status'],
            'error' => Cache::get('system:backup:last')['error'],
        ]);
    }

    public function test_successful_backup_is_saved_for_the_admin_panel(): void
    {
        Cache::forever('system:backup:processing', true);
        Artisan::shouldReceive('call')
            ->once()
            ->with('backup:run')
            ->andReturn(0);

        (new CreateBackup)->handle();

        $this->assertFalse(Cache::has('system:backup:processing'));
        $this->assertSame('completed', Cache::get('system:backup:last')['status']);
    }
}
