<?php

namespace App\Services\MediaMigration;

use App\Models\MediaMigration;
use App\Enums\Media\MediaMigrationStatus;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * 媒体压缩包服务：磁盘打包 / 解压恢复 / 完整性校验。
 *
 * - 打包使用 STORE（不压缩）模式：媒体文件本身已是压缩格式，STORE 打包速度快、
 *   产物体积 ≈ 原始体积，用于服务器搬迁场景。
 * - 压缩包内嵌 manifest（.media-migration-manifest.jsonl，含每个文件的 sha1），
 *   解压后按 manifest 做逐文件校验（存在性 / 大小 / 哈希），保证迁移可靠性。
 */
class MediaArchiveService
{
    // 压缩包内嵌的 manifest 条目名
    public const MANIFEST_ENTRY = '.media-migration-manifest.jsonl';

    // 每 N 个文件 / N 字节刷新一次数据库进度
    private const PROGRESS_FILE_STEP = 500;

    private const PROGRESS_BYTES_STEP = 128 * 1024 * 1024;

    private MediaMigrationService $migrationService;

    public function __construct(MediaMigrationService $migrationService)
    {
        $this->migrationService = $migrationService;
    }

    /**
     * 将迁移记录对应的源磁盘打包为 zip
     */
    public function createArchive(MediaMigration $migration): void
    {
        $disk = $migration->source_disk;
        $storage = Storage::disk($disk);

        $archiveName = 'media-'.str($disk)->slug().'-'.now()->format('Ymd-His').'-'.$migration->id.'.zip';
        $archiveRelativePath = $this->migrationService->archivesDir().'/'.$archiveName;
        $archiveAbsPath = $this->migrationService->getWorkDisk()->path($archiveRelativePath);

        $zip = new \ZipArchive();

        if ($zip->open($archiveAbsPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create zip archive at '.$archiveAbsPath);
        }

        $migration->archive_path = $archiveRelativePath;
        $migration->save();

        $files = $storage->allFiles();

        $manifestLines = [];
        $bytesTotal = 0;
        $bytesDone = 0;
        $filesDone = 0;
        $lastFlushBytes = 0;
        $lastFlushFiles = 0;
        $failed = 0;

        foreach ($files as $path) {
            // 中途取消：关闭并清理半成品
            if ((($filesDone + $failed) % 200) === 0 && $this->isCancelled($migration)) {
                $zip->close();

                if (file_exists($archiveAbsPath)) {
                    unlink($archiveAbsPath);
                }

                return;
            }

            $absPath = $storage->path($path);

            if (! is_file($absPath) || ! is_readable($absPath)) {
                $failed++;
                $this->migrationService->appendFailure($migration->id, $path, 'scan', 'File not readable.');

                continue;
            }

            $size = filesize($absPath);
            $hash = sha1_file($absPath);

            if ($hash === false) {
                $failed++;
                $this->migrationService->appendFailure($migration->id, $path, 'scan', 'Failed to hash file.');

                continue;
            }

            $zip->addFile($absPath, $path);
            // 媒体文件已是压缩格式，STORE 模式打包速度最快
            $zip->setCompressionName($path, \ZipArchive::CM_STORE);

            $manifestLines[] = json_encode(['p' => $path, 's' => $size, 'h' => $hash]);

            $bytesTotal += $size;
            $bytesDone += $size;
            $filesDone++;

            // 进度落库（限流）
            if ($filesDone - $lastFlushFiles >= self::PROGRESS_FILE_STEP || $bytesDone - $lastFlushBytes >= self::PROGRESS_BYTES_STEP) {
                $migration->files_total = count($files);
                $migration->bytes_total = $bytesTotal;
                $migration->files_done = $filesDone;
                $migration->bytes_done = $bytesDone;
                $migration->files_failed = $failed;
                $migration->save();

                $lastFlushFiles = $filesDone;
                $lastFlushBytes = $bytesDone;
            }
        }

        // 内嵌 manifest（解压端校验依据）
        $zip->addFromString(self::MANIFEST_ENTRY, implode("\n", $manifestLines)."\n");
        $zip->close();

        $migration->files_total = count($files);
        $migration->bytes_total = $bytesTotal;
        $migration->files_done = $filesDone;
        $migration->bytes_done = $bytesDone;
        $migration->files_failed = $failed;
        $migration->save();

        $this->migrationService->finalize($migration, [
            'archive_name' => $archiveName,
            'archive_size' => filesize($archiveAbsPath),
        ]);
    }

    /**
     * 解压恢复迁移记录对应的压缩包到其源磁盘，并做完整性校验。
     *
     * 返回 true=完成（可能带校验差异），false=中途取消。
     */
    public function extractArchive(MediaMigration $migration, string $disk): bool
    {
        $archiveRelativePath = $migration->archive_path;
        $archiveAbsPath = $this->migrationService->getWorkDisk()->path($archiveRelativePath);
        $diskRoot = Storage::disk($disk)->path('');

        $zip = new \ZipArchive();

        if ($zip->open($archiveAbsPath) !== true) {
            throw new \RuntimeException('Failed to open zip archive: '.$archiveRelativePath);
        }

        // 读取内嵌 manifest
        $manifestContent = $zip->getFromName(self::MANIFEST_ENTRY);

        if ($manifestContent === false) {
            $zip->close();
            throw new \RuntimeException('Archive manifest entry missing. The archive may be corrupted or not created by this tool.');
        }

        $manifest = [];

        foreach (explode("\n", trim($manifestContent)) as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $entry = json_decode($line);

            if (! empty($entry->p)) {
                $manifest[] = $entry;
            }
        }

        $migration->files_total = count($manifest);
        $migration->bytes_total = array_sum(array_map(fn ($e) => $e->s, $manifest));
        $migration->save();

        // ---- 阶段一：解压（带 Zip Slip 防护 + 进度）----
        $bytesDone = 0;
        $filesDone = 0;
        $extractFailed = 0;

        foreach ($manifest as $entry) {
            if (($filesDone % 200) === 0 && $this->isCancelled($migration)) {
                $zip->close();

                return false;
            }

            if (! $this->isSafeEntryName($entry->p)) {
                $this->migrationService->appendFailure($migration->id, $entry->p, 'extract', 'Unsafe entry name in archive.');
                $extractFailed++;
                continue;
            }

            $zipIndex = $zip->locateName($entry->p);

            if ($zipIndex === false) {
                $this->migrationService->appendFailure($migration->id, $entry->p, 'extract', 'Entry missing in archive.');
                $extractFailed++;
                continue;
            }

            $stream = $zip->getStream($entry->p);

            if ($stream === false) {
                $this->migrationService->appendFailure($migration->id, $entry->p, 'extract', 'Failed to open entry stream.');
                $extractFailed++;
                continue;
            }

            $targetPath = rtrim($diskRoot, '/').'/'.$entry->p;
            $targetDir = dirname($targetPath);

            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $out = fopen($targetPath, 'wb');

            if ($out === false) {
                fclose($stream);
                $this->migrationService->appendFailure($migration->id, $entry->p, 'extract', 'Failed to write target file.');
                $extractFailed++;
                continue;
            }

            stream_copy_to_stream($stream, $out);

            fclose($stream);
            fclose($out);

            $bytesDone += $entry->s;
            $filesDone++;

            if ($filesDone % self::PROGRESS_FILE_STEP === 0) {
                $migration->files_done = $filesDone;
                $migration->files_failed = $extractFailed;
                $migration->bytes_done = $bytesDone;
                $migration->save();
            }
        }

        $zip->close();

        $migration->files_done = $filesDone;
        $migration->files_failed = $extractFailed;
        $migration->bytes_done = $bytesDone;
        $migration->save();

        if ($this->isCancelled($migration)) {
            return false;
        }

        // ---- 阶段二：校验（存在性 / 大小 / 哈希）----
        $missing = 0;
        $mismatch = 0;
        $verified = 0;

        foreach ($manifest as $entry) {
            if (($verified % 500) === 0 && $this->isCancelled($migration)) {
                return false;
            }

            $targetPath = rtrim($diskRoot, '/').'/'.$entry->p;

            if (! is_file($targetPath)) {
                $missing++;
                $this->migrationService->appendFailure($migration->id, $entry->p, 'verify', 'File missing after extraction.');
                continue;
            }

            if (filesize($targetPath) !== (int) $entry->s) {
                $mismatch++;
                $this->migrationService->appendFailure($migration->id, $entry->p, 'verify', 'Size mismatch after extraction.');
                continue;
            }

            if (! empty($entry->h) && sha1_file($targetPath) !== $entry->h) {
                $mismatch++;
                $this->migrationService->appendFailure($migration->id, $entry->p, 'verify', 'Checksum mismatch after extraction.');
                continue;
            }

            $verified++;
        }

        $migration->verify_missing = $missing;
        $migration->verify_mismatch = $mismatch;

        // 校验差异计入失败数，便于报告与重试入口判断
        $migration->files_failed += ($missing + $mismatch);

        $this->migrationService->finalize($migration, [
            'extracted_to_disk' => $disk,
            'verified_files' => $verified,
        ]);

        return true;
    }

    /**
     * 验证压缩包完整性（上传完成后调用）：可打开 + 含内嵌 manifest
     */
    public function validateArchive(string $archiveRelativePath): bool
    {
        $absPath = $this->migrationService->getWorkDisk()->path($archiveRelativePath);

        $zip = new \ZipArchive();

        if ($zip->open($absPath) !== true) {
            return false;
        }

        $hasManifest = $zip->locateName(self::MANIFEST_ENTRY) !== false;

        $zip->close();

        return $hasManifest;
    }

    /**
     * Zip Slip 防护：禁止绝对路径、.. 上跳、phar 流包装
     */
    private function isSafeEntryName(string $name): bool
    {
        if ($name === '' || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
            return false;
        }

        if (str_contains($name, '://') || str_contains($name, "\0")) {
            return false;
        }

        $segments = explode('/', str_replace('\\', '/', $name));

        return ! in_array('..', $segments, true);
    }

    private function isCancelled(MediaMigration $migration): bool
    {
        $migration->refresh();

        return $migration->status !== MediaMigrationStatus::RUNNING;
    }
}
