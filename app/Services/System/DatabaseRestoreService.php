<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DatabaseRestoreService
{
    public const WORK_DIR = 'backup-restore';

    private const MAX_ARCHIVE_BYTES = 10 * 1024 * 1024 * 1024;

    private const MAX_DUMP_BYTES = 10 * 1024 * 1024 * 1024;

    public function disk()
    {
        return Storage::disk('local');
    }

    public function backupsDir(): string
    {
        return 'backups';
    }

    public function incomingDir(): string
    {
        return self::WORK_DIR.'/incoming';
    }

    public function candidatePath(string $name): string
    {
        if ($name !== basename($name) || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new RuntimeException('Invalid backup filename.');
        }

        if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\.zip\z/i', $name)) {
            throw new RuntimeException('Invalid backup filename.');
        }

        return $this->backupsDir().'/'.$name;
    }

    public function validateArchive(string $relativePath): array
    {
        $path = $this->disk()->path($relativePath);

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Backup archive not found.');
        }

        $size = filesize($path);

        if ($size === false || $size <= 0 || $size > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('Backup archive size is invalid.');
        }

        $connection = config('database.default');
        $config = config("database.connections.{$connection}", []);
        $driver = strtolower((string) ($config['driver'] ?? ''));

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Database restore currently supports MySQL and MariaDB only.');
        }

        $database = (string) ($config['database'] ?? '');
        $expected = "{$driver}-{$database}.sql";
        $zip = $this->openArchive($path);

        $matches = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entry = $zip->statIndex($index);
            $name = $entry['name'] ?? '';

            // Spatie backups may preserve absolute source paths in the ZIP. We never
            // extract those entries; only the single database dump below is read.
            if (basename($name) === $expected && ! str_ends_with($name, '/')) {
                $matches[] = $name;
            }
        }

        if (count($matches) !== 1) {
            $zip->close();
            throw new RuntimeException("Backup archive must contain exactly one {$expected} database dump.");
        }

        $dump = $zip->statName($matches[0]);
        $dumpSize = (int) ($dump['size'] ?? 0);

        if ($dumpSize <= 0 || $dumpSize > self::MAX_DUMP_BYTES) {
            $zip->close();
            throw new RuntimeException('Database dump size is invalid.');
        }

        $dumpHash = $this->hashEntry($zip, $matches[0]);
        $zip->close();

        return [
            'name' => basename($relativePath),
            'path' => $relativePath,
            'archive_size' => $size,
            'archive_sha256' => hash_file('sha256', $path),
            'dump_entry' => $matches[0],
            'dump_size' => $dumpSize,
            'dump_sha256' => $dumpHash,
            'connection' => $connection,
            'driver' => $driver,
            'database' => $database,
            'validated_at' => now()->toIso8601String(),
        ];
    }

    public function createEmergencyBackup(): void
    {
        $exitCode = Artisan::call('backup:run', ['--only-db' => true, '--filename-suffix' => '-before-restore']);

        if ($exitCode !== 0) {
            throw new RuntimeException('Emergency database backup failed; restore was not started.');
        }

        $latest = collect($this->disk()->files($this->backupsDir()))
            ->filter(fn ($path) => str_ends_with(strtolower($path), '.zip'))
            ->sortByDesc(fn ($path) => $this->disk()->lastModified($path))
            ->first();

        if (! $latest) {
            throw new RuntimeException('Emergency database backup was not created.');
        }

        $this->validateArchive($latest);
    }

    public function extractDump(string $relativePath, string $entry): string
    {
        $path = $this->disk()->path($relativePath);
        $zip = $this->openArchive($path);

        $stream = $zip->getStream($entry);
        if ($stream === false) {
            $zip->close();
            throw new RuntimeException('Database dump is missing from the backup archive.');
        }

        $dir = storage_path('app/'.self::WORK_DIR.'/dumps');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            fclose($stream);
            $zip->close();
            throw new RuntimeException('Unable to create private restore directory.');
        }

        $dumpPath = tempnam($dir, 'restore-');
        $out = $dumpPath === false ? false : fopen($dumpPath, 'wb');

        if ($out === false) {
            fclose($stream);
            $zip->close();
            throw new RuntimeException('Unable to create temporary database dump.');
        }

        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);
        $zip->close();
        chmod($dumpPath, 0600);

        return $dumpPath;
    }

    public function import(string $dumpPath): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}", []);
        $driver = strtolower((string) ($config['driver'] ?? ''));

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Database restore currently supports MySQL and MariaDB only.');
        }

        $binary = $driver === 'mariadb' ? 'mariadb' : 'mysql';
        $credentials = tempnam(sys_get_temp_dir(), 'db-restore-');

        if ($credentials === false) {
            throw new RuntimeException('Unable to create temporary database credentials.');
        }

        try {
            file_put_contents($credentials, "[client]\nuser = ".($config['username'] ?? '')."\npassword = ".($config['password'] ?? '')."\nhost = ".($config['host'] ?? '127.0.0.1')."\nport = ".($config['port'] ?? 3306)."\n");
            chmod($credentials, 0600);

            $command = [$binary, "--defaults-extra-file={$credentials}", (string) ($config['database'] ?? '')];
            $process = new Process($command, null, null, fopen($dumpPath, 'rb'), 60 * 60 * 3);
            $process->mustRun();
        } finally {
            @unlink($credentials);
        }
    }

    private function hashEntry(ZipArchive $zip, string $entry): string
    {
        $stream = $zip->getStream($entry);
        if ($stream === false) {
            throw new RuntimeException('Database dump cannot be read.');
        }

        $hash = hash_init('sha256');
        $bytes = 0;
        $lineBuffer = '';
        while (! feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                fclose($stream);
                throw new RuntimeException('Database dump cannot be read.');
            }
            $lineBuffer .= $chunk;
            if (preg_match('/(?m)^\s*\\\\[^\r\n]*|(?m)^\s*(?:source|system|\.\\s)/i', $lineBuffer)) {
                fclose($stream);
                throw new RuntimeException('Database dump contains unsupported client commands.');
            }
            $lineBuffer = substr($lineBuffer, -4096);
            $bytes += strlen($chunk);
            hash_update($hash, $chunk);
        }
        fclose($stream);

        if ($bytes <= 0 || $bytes > self::MAX_DUMP_BYTES) {
            throw new RuntimeException('Database dump size is invalid.');
        }

        return hash_final($hash);
    }

    private function openArchive(string $path): ZipArchive
    {
        $zip = new ZipArchive();
        $result = $zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS);

        if ($result !== true) {
            throw new RuntimeException('Backup archive is corrupted or cannot be opened.');
        }

        $password = config('backup.backup.password');
        if (is_string($password) && $password !== '') {
            $zip->setPassword($password);
        }

        return $zip;
    }

    private function isSafeEntryName(string $name): bool
    {
        if ($name === '' || str_starts_with($name, '/') || str_starts_with($name, '\\') || str_contains($name, "\0") || str_contains($name, '://')) {
            return false;
        }

        return ! in_array('..', explode('/', str_replace('\\', '/', $name)), true);
    }
}
