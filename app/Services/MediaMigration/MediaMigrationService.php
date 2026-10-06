<?php

namespace App\Services\MediaMigration;

use App\Constants\Filesystem;
use App\Models\DataStat;
use App\Models\Media;
use App\Models\MediaMigration;
use App\Enums\Media\MediaMigrationStatus;
use App\Enums\Media\MediaMigrationType;
use App\Jobs\MediaMigration\CreateMediaArchiveJob;
use App\Jobs\MediaMigration\ExtractMediaArchiveJob;
use App\Jobs\MediaMigration\MigrateDiskFilesJob;
use Illuminate\Support\Facades\Storage;

/**
 * 媒体文件迁移编排服务。
 *
 * 三种迁移类型：
 * - archive       本地磁盘打包 zip（换服务器：下载 → 上传 → 解压恢复）
 * - extract       解压恢复 + 完整性校验
 * - disk_transfer 磁盘间迁移（如本地 → S3），同步更新数据库记录与用量统计
 *
 * 状态与断点：每次迁移在 media_migrations 表有一行状态记录，
 * manifest（JSONL，一行一个文件）保存在 storage/app/media-migration/{id}/ 下，
 * 迁移任务按 cursor 游标分批自链式执行，中断后可从游标处继续。
 */
class MediaMigrationService
{
    /** 本地 local 磁盘根（storage/app）下的工作目录 */
    public const WORK_DIR = 'media-migration';

    public function getWorkDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('local');
    }

    public function migrationDir(int $migrationId): string
    {
        return self::WORK_DIR."/{$migrationId}";
    }

    public function manifestPath(int $migrationId): string
    {
        return $this->migrationDir($migrationId).'/manifest.jsonl';
    }

    public function failuresPath(int $migrationId): string
    {
        return $this->migrationDir($migrationId).'/failures.jsonl';
    }

    public function archivesDir(): string
    {
        return self::WORK_DIR.'/archives';
    }

    /**
     * 可参与迁移的磁盘（后台 var/config/filesystems/disks.php 中配置的轮询磁盘）
     *
     * @return array<int, array{id: string, driver: string, name: string|null, description: string|null, is_s3: bool, is_local: bool}>
     */
    public function getMigrationDisks(): array
    {
        $disks = [];

        foreach (config('filesystems.disks') as $diskId => $diskConfig) {
            if (in_array($diskId, array_keys(config('filesystems.system_disks')))) {
                continue;
            }

            $disks[] = [
                'id' => $diskId,
                'driver' => $diskConfig['driver'] ?? 'local',
                'name' => $diskConfig['name'] ?? null,
                'description' => $diskConfig['description'] ?? null,
                'is_s3' => ($diskConfig['driver'] ?? '') === 's3',
                'is_local' => ($diskConfig['driver'] ?? 'local') === 'local',
            ];
        }

        return $disks;
    }

    /**
     * 扫描磁盘全部文件并生成 manifest（断点续传的文件清单）
     *
     * @return array{files: int, bytes: int}
     */
    public function scanDisk(string $disk): array
    {
        $storage = Storage::disk($disk);

        $files = $storage->allFiles();

        return [
            'files' => count($files),
            'bytes' => array_sum(array_map(fn ($path) => $storage->size($path), $files)),
        ];
    }

    /**
     * 创建「本地打包」迁移并派发队列任务
     */
    public function startArchive(string $disk): MediaMigration
    {
        if (! array_key_exists($disk, config('filesystems.disks')) || ($disk === Filesystem::EXTERNAL_DISK_NAME)) {
            throw new \InvalidArgumentException("Disk [{$disk}] is not available.");
        }

        if (! $this->getWorkDisk()->exists($this->archivesDir())) {
            $this->getWorkDisk()->makeDirectory($this->archivesDir());
        }

        $migration = MediaMigration::create([
            'type' => MediaMigrationType::ARCHIVE,
            'status' => MediaMigrationStatus::RUNNING,
            'source_disk' => $disk,
            'target_disk' => '',
            'started_at' => now(),
        ]);

        $this->ensureMigrationDir($migration->id);

        dispatch(new CreateMediaArchiveJob($migration->id));

        return $migration;
    }

    /**
     * 创建「解压恢复」迁移并派发队列任务
     *
     * @param string $archiveName 压缩包文件名（archives 目录下）
     * @param string $disk 解压目标磁盘（通常与打包时的源磁盘同名）
     */
    public function startExtract(string $archiveName, string $disk): MediaMigration
    {
        $archiveName = basename($archiveName);
        $archivePath = $this->archivesDir().'/'.$archiveName;

        if (! $this->getWorkDisk()->exists($archivePath)) {
            throw new \InvalidArgumentException('Archive file not found.');
        }

        if (! array_key_exists($disk, config('filesystems.disks')) || ($disk === Filesystem::EXTERNAL_DISK_NAME)) {
            throw new \InvalidArgumentException("Disk [{$disk}] is not available.");
        }

        $migration = MediaMigration::create([
            'type' => MediaMigrationType::EXTRACT,
            'status' => MediaMigrationStatus::RUNNING,
            'source_disk' => $disk,
            'archive_path' => $archivePath,
            'started_at' => now(),
        ]);

        $this->ensureMigrationDir($migration->id);

        dispatch(new ExtractMediaArchiveJob($migration->id));

        return $migration;
    }

    /**
     * 创建（或恢复）「磁盘间迁移」：将 source 磁盘全部文件复制到 target 磁盘，
     * 并把数据库中的磁盘引用更新为 target。
     *
     * @param bool $checksum 是否做 sha1 全量校验（更慢更可靠）
     */
    public function startDiskTransfer(string $source, string $target, bool $checksum = false): MediaMigration
    {
        if ($source === $target) {
            throw new \InvalidArgumentException('Source and target disks must differ.');
        }

        $disks = collect($this->getMigrationDisks())->pluck('id')->all();

        if (! in_array($source, $disks) || ! in_array($target, $disks)) {
            throw new \InvalidArgumentException('Source or target disk is not available.');
        }

        // 已存在同方向的未完成迁移 → 断点续传
        $existing = MediaMigration::query()
            ->where('type', MediaMigrationType::DISK_TRANSFER)
            ->where('source_disk', $source)
            ->where('target_disk', $target)
            ->where('status', MediaMigrationStatus::RUNNING)
            ->latest('id')
            ->first();

        if ($existing) {
            dispatch(new MigrateDiskFilesJob($existing->id));

            return $existing;
        }

        // 已存在同方向的失败迁移 → 保留历史，从失败游标继续
        // （前提：失败后没有更晚完成的同方向迁移，否则视为全新迁移重新扫描）
        $failed = MediaMigration::query()
            ->where('type', MediaMigrationType::DISK_TRANSFER)
            ->where('source_disk', $source)
            ->where('target_disk', $target)
            ->where('status', MediaMigrationStatus::FAILED)
            ->latest('id')
            ->first();

        $lastCompletedId = MediaMigration::query()
            ->where('type', MediaMigrationType::DISK_TRANSFER)
            ->where('source_disk', $source)
            ->where('target_disk', $target)
            ->where('status', MediaMigrationStatus::COMPLETED)
            ->max('id') ?? 0;

        if ($failed && $failed->id > $lastCompletedId) {
            $failed->status = MediaMigrationStatus::RUNNING;
            $failed->error = null;
            $failed->save();

            dispatch(new MigrateDiskFilesJob($failed->id));

            return $failed;
        }

        $migration = MediaMigration::create([
            'type' => MediaMigrationType::DISK_TRANSFER,
            'status' => MediaMigrationStatus::RUNNING,
            'source_disk' => $source,
            'target_disk' => $target,
            'options' => ['checksum' => $checksum],
            'started_at' => now(),
        ]);

        $this->ensureMigrationDir($migration->id);

        // 扫描磁盘并写入 manifest
        $this->buildManifestForDisk($migration, $source);

        dispatch(new MigrateDiskFilesJob($migration->id));

        return $migration;
    }

    /**
     * 只重试某次迁移中失败的文件（生成一个新的迁移任务）
     */
    public function retryFailed(MediaMigration $migration): MediaMigration
    {
        if ($migration->type !== MediaMigrationType::DISK_TRANSFER || ! $migration->hasFailures()) {
            throw new \InvalidArgumentException('This migration cannot be retried.');
        }

        $failures = $this->readFailures($migration->id);

        if (empty($failures)) {
            throw new \InvalidArgumentException('No failed files recorded.');
        }

        $paths = array_values(array_unique(array_column($failures, 'p')));

        $retry = MediaMigration::create([
            'type' => MediaMigrationType::DISK_TRANSFER,
            'status' => MediaMigrationStatus::RUNNING,
            'source_disk' => $migration->source_disk,
            'target_disk' => $migration->target_disk,
            'options' => array_merge($migration->options ?? [], ['retry_of' => $migration->id]),
            'started_at' => now(),
        ]);

        $this->ensureMigrationDir($retry->id);

        $source = Storage::disk($migration->source_disk);
        $bytes = 0;

        $handle = fopen($this->getWorkDisk()->path($this->manifestPath($retry->id)), 'w');

        foreach ($paths as $path) {
            if (! $source->exists($path)) {
                continue;
            }

            $size = $source->size($path);
            $bytes += $size;

            fwrite($handle, json_encode(['p' => $path, 's' => $size])."\n");
        }

        fclose($handle);

        $retry->files_total = count($paths);
        $retry->bytes_total = $bytes;
        $retry->save();

        dispatch(new MigrateDiskFilesJob($retry->id));

        return $retry;
    }

    public function cancel(MediaMigration $migration): void
    {
        if (! $migration->status->isRunning()) {
            return;
        }

        $migration->status = MediaMigrationStatus::CANCELLED;
        $migration->finished_at = now();
        $migration->save();

        // 打包中途取消：清理未完成的半成品压缩包
        if ($migration->type->isArchive() && $migration->archive_path) {
            $this->getWorkDisk()->delete($migration->archive_path);
        }
    }

    /**
     * 写入磁盘 manifest（JSONL：{"p":路径,"s":字节数}）
     */
    public function buildManifestForDisk(MediaMigration $migration, string $disk): void
    {
        $storage = Storage::disk($disk);
        $files = $storage->allFiles();

        $this->ensureMigrationDir($migration->id);

        $manifestLocalPath = $this->getWorkDisk()->path($this->manifestPath($migration->id));
        $handle = fopen($manifestLocalPath, 'w');

        $bytes = 0;

        foreach ($files as $path) {
            $size = $storage->size($path);
            $bytes += $size;

            fwrite($handle, json_encode(['p' => $path, 's' => $size])."\n");
        }

        fclose($handle);

        $migration->files_total = count($files);
        $migration->bytes_total = $bytes;
        $migration->cursor = 0;
        $migration->save();
    }

    /**
     * 逐行读取 manifest 的一段（断点续传：跳过 offset 行，取 limit 行）
     *
     * @return \Generator<int, object>
     */
    public function readManifestSlice(int $migrationId, int $offset, int $limit): \Generator
    {
        $path = $this->getWorkDisk()->path($this->manifestPath($migrationId));

        if (! file_exists($path)) {
            return;
        }

        $handle = fopen($path, 'r');
        $line = 0;
        $yielded = 0;

        while (($buffer = fgets($handle)) !== false) {
            if ($line >= $offset) {
                $entry = json_decode(rtrim($buffer, "\n"));

                if (! empty($entry->p)) {
                    yield $entry;
                }

                $yielded++;

                if ($yielded >= $limit) {
                    break;
                }
            }

            $line++;
        }

        fclose($handle);
    }

    public function appendFailure(int $migrationId, string $path, string $stage, string $error): void
    {
        $this->ensureMigrationDir($migrationId);

        $handle = fopen($this->getWorkDisk()->path($this->failuresPath($migrationId)), 'a');

        fwrite($handle, json_encode([
            'p' => $path,
            'stage' => $stage,
            'e' => mb_substr($error, 0, 500),
        ], JSON_UNESCAPED_UNICODE)."\n");

        fclose($handle);
    }

    /**
     * @return array<int, array{p: string, stage: string, e: string}>
     */
    public function readFailures(int $migrationId, int $limit = 2000): array
    {
        $path = $this->getWorkDisk()->path($this->failuresPath($migrationId));

        if (! file_exists($path)) {
            return [];
        }

        $failures = [];
        $handle = fopen($path, 'r');

        while (($buffer = fgets($handle)) !== false) {
            $entry = json_decode(rtrim($buffer, "\n"), true);

            if (! empty($entry['p'])) {
                $failures[] = $entry;
            }

            if (count($failures) >= $limit) {
                break;
            }
        }

        fclose($handle);

        return $failures;
    }

    /**
     * 完成迁移：生成报告、归档用量统计（磁盘迁移场景）
     */
    public function finalize(MediaMigration $migration, array $extra = []): void
    {
        $statsMoved = false;

        // 磁盘迁移且无失败时：把 data_stats 用量从源磁盘合并到目标磁盘
        if ($migration->type->isDiskTransfer() && $migration->files_failed === 0) {
            $this->mergeDataStats($migration->source_disk, $migration->target_disk);
            $statsMoved = true;
        }

        $report = array_merge([
            'files_total' => $migration->files_total,
            'files_done' => $migration->files_done,
            'files_failed' => $migration->files_failed,
            'bytes_total' => $migration->bytes_total,
            'bytes_done' => $migration->bytes_done,
            'verify_missing' => $migration->verify_missing,
            'verify_mismatch' => $migration->verify_mismatch,
            'duration_seconds' => $migration->started_at
                ? max(0, now()->diffInSeconds($migration->started_at))
                : null,
            'failures_sample' => array_slice($this->readFailures($migration->id, 50), 0, 50),
            'failures_log' => $this->getWorkDisk()->exists($this->failuresPath($migration->id))
                ? $this->failuresPath($migration->id)
                : null,
            'stats_moved' => $statsMoved,
        ], $extra);

        $migration->report = $report;
        $migration->status = MediaMigrationStatus::COMPLETED;
        $migration->finished_at = now();
        $migration->save();
    }

    /**
     * 把源磁盘的用量统计（data_stats）合并到目标磁盘
     */
    public function mergeDataStats(string $source, string $target): void
    {
        DataStat::where('disk', $source)->get()->each(function (DataStat $stat) use ($target) {
            $targetStat = DataStat::firstOrCreate([
                'media_type' => $stat->media_type,
                'disk' => $target,
            ]);

            $targetStat->content_size = intval($targetStat->content_size) + intval($stat->content_size);
            $targetStat->content_items = intval($targetStat->content_items) + intval($stat->content_items);
            $targetStat->save();

            $stat->delete();
        });
    }

    /**
     * 批量更新数据库中的磁盘引用（media 表 source/thumbnail 两个维度）
     *
     * @param array $paths 相对路径列表
     * @return int 受影响的行数
     */
    public function updateDatabaseReferences(string $source, string $target, array $paths): int
    {
        if (empty($paths)) {
            return 0;
        }

        $rows = Media::where('disk', $source)->whereIn('source_path', $paths)->update(['disk' => $target]);
        $rows += Media::where('thumbnail_disk', $source)->whereIn('thumbnail_path', $paths)->update(['thumbnail_disk' => $target]);

        return $rows;
    }

    /**
     * 列出已有压缩包
     *
     * @return array<int, array{name: string, size: string, date: string}>
     */
    public function listArchives(): array
    {
        $disk = $this->getWorkDisk();

        if (! $disk->exists($this->archivesDir())) {
            return [];
        }

        return collect($disk->files($this->archivesDir()))
            ->filter(fn ($file) => str_ends_with(strtolower($file), '.zip'))
            ->map(fn ($file) => [
                'name' => basename($file),
                'path' => $file,
                'size' => file_size_format($disk->size($file)),
                'date' => \Illuminate\Support\Carbon::parse($disk->lastModified($file))->format('d M Y, H:i'),
            ])
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    /**
     * 将主存储磁盘（STATIC_STORAGE_DISK）切换到指定磁盘。
     *
     * 用户头像/封面、群组头像等直接使用 static storage disk（无独立 disk 字段），
     * 磁盘迁移完成后必须切换主磁盘，否则这些资源的 URL 仍指向旧磁盘。
     */
    public function switchStaticDisk(string $disk): bool
    {
        if (! array_key_exists($disk, config('filesystems.disks'))) {
            throw new \InvalidArgumentException("Disk [{$disk}] is not available.");
        }

        $envPath = base_path('.env');

        if (! file_exists($envPath) || ! is_writable($envPath)) {
            return false;
        }

        $content = file_get_contents($envPath);

        if (preg_match('/^STATIC_STORAGE_DISK=.*$/m', $content)) {
            $content = preg_replace('/^STATIC_STORAGE_DISK=.*$/m', 'STATIC_STORAGE_DISK='.$disk, $content);
        }
        else {
            $content = rtrim($content)."\nSTATIC_STORAGE_DISK={$disk}\n";
        }

        file_put_contents($envPath, $content);

        return true;
    }

    public function deleteMigrationData(MediaMigration $migration): void
    {
        $dir = $this->migrationDir($migration->id);

        if ($this->getWorkDisk()->exists($dir)) {
            $this->getWorkDisk()->deleteDirectory($dir);
        }
    }

    private function ensureMigrationDir(int $migrationId): void
    {
        $dir = $this->migrationDir($migrationId);

        if (! $this->getWorkDisk()->exists($dir)) {
            $this->getWorkDisk()->makeDirectory($dir);
        }
    }
}
