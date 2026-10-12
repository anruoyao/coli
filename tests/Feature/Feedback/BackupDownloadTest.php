<?php

namespace Tests\Feature\Feedback;

use App\Http\Controllers\Admin\Config\ConfigController;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BackupDownloadTest extends TestCase
{
    public function test_large_backup_is_returned_as_a_file_response(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');
        $name = '2026-10-12-17-13-09.zip';
        $file = fopen($disk->path("backups/{$name}"), 'wb');
        ftruncate($file, 160 * 1024 * 1024);
        fclose($file);

        $response = (new ConfigController())->downloadBackup($name);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame($disk->path("backups/{$name}"), $response->getFile()->getPathname());
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString($name, $response->headers->get('Content-Disposition'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function test_missing_backup_returns_404(): void
    {
        Storage::fake('local');

        try {
            (new ConfigController())->downloadBackup('missing.zip');
            $this->fail('Missing backups must return 404.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_invalid_names_cannot_download_files_outside_backups(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('outside.zip', 'private data');

        foreach (['../outside.zip', '..\\outside.zip', '/outside.zip', 'outside.txt'] as $name) {
            try {
                (new ConfigController())->downloadBackup($name);
                $this->fail("Invalid backup name was accepted: {$name}");
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
    }

    public function test_download_route_uses_admin_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.config.backup.download');

        $this->assertNotNull($route);
        $this->assertContains('GET', $route->methods());
        $this->assertContains('admin-area', $route->gatherMiddleware());
    }
}
