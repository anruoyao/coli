<?php

namespace Tests\Feature\Feedback;

use App\Services\System\DatabaseRestoreService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class DatabaseRestoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql',
                'database' => 'testing',
            ],
        ]);
    }

    public function test_valid_database_backup_is_inspected_without_extracting_application_files(): void
    {
        $this->putZip('backups/backup.zip', [
            'home/site/mysql-testing.sql' => "CREATE TABLE `health_check` (id int);\n",
            'home/site/.env' => 'must never be extracted',
        ]);

        $details = app(DatabaseRestoreService::class)->validateArchive('backups/backup.zip');

        $this->assertSame('home/site/mysql-testing.sql', $details['dump_entry']);
        $this->assertSame('testing', $details['database']);
        $this->assertNotEmpty($details['archive_sha256']);
        $this->assertFalse(Storage::disk('local')->exists('home/site/.env'));
    }

    public function test_archive_without_current_database_dump_is_rejected(): void
    {
        $this->putZip('backups/backup.zip', ['mysql-other.sql' => 'dump']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must contain exactly one');

        app(DatabaseRestoreService::class)->validateArchive('backups/backup.zip');
    }

    public function test_duplicate_database_dump_entries_are_rejected(): void
    {
        $this->putZip('backups/backup.zip', [
            'one/mysql-testing.sql' => 'dump one',
            'two/mysql-testing.sql' => 'dump two',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must contain exactly one');

        app(DatabaseRestoreService::class)->validateArchive('backups/backup.zip');
    }

    public function test_unsupported_database_driver_is_rejected(): void
    {
        config(['database.connections.mysql.driver' => 'pgsql']);
        $this->putZip('backups/backup.zip', ['mysql-testing.sql' => 'dump']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MySQL and MariaDB only');

        app(DatabaseRestoreService::class)->validateArchive('backups/backup.zip');
    }

    public function test_mysql_client_commands_are_rejected_before_import(): void
    {
        $this->putZip('backups/backup.zip', [
            'mysql-testing.sql' => "CREATE TABLE safe_table (id int);\n\\! touch /tmp/should-not-run\n",
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported client commands');

        app(DatabaseRestoreService::class)->validateArchive('backups/backup.zip');
    }

    public function test_backup_filename_cannot_escape_backup_directory(): void
    {
        $this->expectException(RuntimeException::class);

        app(DatabaseRestoreService::class)->candidatePath('../outside.zip');
    }

    private function putZip(string $path, array $entries): void
    {
        $absolute = Storage::disk('local')->path($path);
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0755, true);
        }

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
    }
}
