<?php

namespace App\Jobs\MediaMigration;

use App\Models\MediaMigration;
use App\Services\MediaMigration\MediaMigrationService;
use App\Enums\Media\MediaMigrationStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * 磁盘间迁移任务（本地 → S3 等）。
 *
 * 自链式分批执行：每次最多处理 500 个文件或运行 40 秒，
 * 处理完一批后把游标（cursor）落库并重新派发自身，实现断点续传——
 * 任何一环中断（进程被杀 / 队列重启 / 出错），都能从游标处继续。
 */
class MigrateDiskFilesJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public $tries = 1;

    // 单次运行的时间预算（秒）与文件数预算
    private const TIME_BUDGET = 40;

    private const FILE_BUDGET = 500;

    // 每处理 N 个文件刷新一次数据库进度 / 批量更新一次数据库引用
    private const PROGRESS_STEP = 100;

    public function __construct(public int $migrationId)
    {
        //
    }

    public function handle(MediaMigrationService $service): void
    {
        $lock = Cache::lock("media-migration:job:{$this->migrationId}", 300);

        if (! $lock->get()) {
            return;
        }

        $hasMoreWork = false;

        try {
            $migration = MediaMigration::find($this->migrationId);

            if (! $migration || ! $migration->status->isRunning()) {
                return;
            }

            $hasMoreWork = $this->processSlice($migration, $service);
        } catch (Throwable $th) {
            $migration = MediaMigration::find($this->migrationId);

            if ($migration && $migration->status->isRunning()) {
                $migration->status = MediaMigrationStatus::FAILED;
                $migration->error = mb_substr($th->getMessage(), 0, 2000);
                $migration->finished_at = now();
                $migration->save();
            }
        } finally {
            // 先释放锁再派发下一批：sync 队列下自链式派发是内联执行的，
            // 不释放锁会导致嵌套任务拿不到锁而静默中断。
            $lock->release();
        }

        if ($hasMoreWork) {
            dispatch(new self($this->migrationId));
        }
    }

    /**
     * 处理一批文件。返回 true 表示还有剩余文件需要继续（外层负责自链式派发）。
     */
    private function processSlice(MediaMigration $migration, MediaMigrationService $service): bool
    {
        $startedAt = microtime(true);

        $source = Storage::disk($migration->source_disk);
        $target = Storage::disk($migration->target_disk);

        $checksum = boolval($migration->options['checksum'] ?? false);

        $cursor = $migration->cursor;
        $processed = 0;

        $pathsBuffer = [];
        $rowsUpdated = intval($migration->report['db_rows_updated'] ?? 0);

        foreach ($service->readManifestSlice($migration->id, $cursor, self::FILE_BUDGET) as $entry) {
            // 时间预算到了：本批到此为止，落库后自派发继续
            if ($processed > 0 && (microtime(true) - $startedAt) > self::TIME_BUDGET) {
                break;
            }

            try {
                // 源文件已不存在（迁移期间被删除的媒体）→ 记失败并跳过
                if (! $source->exists($entry->p)) {
                    $service->appendFailure($migration->id, $entry->p, 'copy', 'Source file no longer exists.');
                    $migration->files_failed++;

                    continue;
                }

                $entrySize = intval($entry->s ?? $source->size($entry->p));

                // 幂等：目标已存在且大小一致 → 跳过复制（断点续传/重试场景）
                $needsCopy = ! $target->exists($entry->p) || $target->size($entry->p) !== $entrySize;

                if ($needsCopy) {
                    $visibility = $source->getVisibility($entry->p);

                    $target->writeStream($entry->p, $source->readStream($entry->p), [
                        'visibility' => $visibility,
                    ]);

                    // 复制后校验：大小必查，哈希可选
                    if ($target->size($entry->p) !== $entrySize) {
                        $service->appendFailure($migration->id, $entry->p, 'verify', 'Target size mismatch after copy.');
                        $migration->files_failed++;

                        continue;
                    }

                    if ($checksum && ! $this->verifyChecksum($source, $target, $entry->p)) {
                        $service->appendFailure($migration->id, $entry->p, 'verify', 'Target checksum mismatch after copy.');
                        $migration->files_failed++;

                        continue;
                    }
                }

                $migration->files_done++;
                $migration->bytes_done += $entrySize;
                $pathsBuffer[] = $entry->p;
            } catch (Throwable $th) {
                $service->appendFailure($migration->id, $entry->p, 'copy', $th->getMessage());
                $migration->files_failed++;
            } finally {
                $processed++;
            }

            // 批量刷新数据库引用 + 进度
            if (count($pathsBuffer) >= self::PROGRESS_STEP) {
                $rowsUpdated += $service->updateDatabaseReferences($migration->source_disk, $migration->target_disk, $pathsBuffer);
                $pathsBuffer = [];
            }

            if ($processed % self::PROGRESS_STEP === 0) {
                $migration->cursor = $cursor + $processed;
                $migration->report = array_merge($migration->report ?? [], ['db_rows_updated' => $rowsUpdated]);
                $migration->save();
            }
        }

        // 刷新剩余的数据库引用
        if (! empty($pathsBuffer)) {
            $rowsUpdated += $service->updateDatabaseReferences($migration->source_disk, $migration->target_disk, $pathsBuffer);
        }

        $migration->cursor = $cursor + $processed;
        $migration->report = array_merge($migration->report ?? [], ['db_rows_updated' => $rowsUpdated]);
        $migration->save();

        // 中途被取消：不再继续派发
        if (! $migration->status->isRunning()) {
            return false;
        }

        // 还有剩余文件 → 由外层自链式派发下一批；否则收尾生成报告
        if ($migration->cursor < $migration->files_total) {
            return true;
        }

        $service->finalize($migration, [
            'db_rows_updated' => $rowsUpdated,
        ]);

        return false;
    }

    private function verifyChecksum($source, $target, string $path): bool
    {
        // 通过流计算哈希，兼容任意驱动（local / s3 / ftp …）
        $context = hash_init('sha1');

        $sourceStream = $source->readStream($path);

        if ($sourceStream === false) {
            return false;
        }

        hash_update_stream($context, $sourceStream);
        fclose($sourceStream);

        $sourceHash = hash_final($context);

        $context = hash_init('sha1');
        $targetStream = $target->readStream($path);

        if ($targetStream === false) {
            return false;
        }

        hash_update_stream($context, $targetStream);
        fclose($targetStream);

        return hash_final($context) === $sourceHash;
    }
}
