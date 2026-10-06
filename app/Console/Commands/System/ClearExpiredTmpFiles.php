<?php

namespace App\Console\Commands\System;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 清理 storage/app/tmp 下超过保留期的临时文件。
 *
 * 背景：视频转码（ConvertAndCompressPostVideo）、缩略图生成等流程会在
 * storage/app/tmp 写入中间产物，正常路径会自行删除，但任务超时被杀、
 * 进程崩溃、上传失败等场景会留下永久残留（曾在生产积压 85MB）。
 * 本命令按文件 mtime 兜底清理，只处理 tmp 目录，绝不触碰其他数据。
 */
class ClearExpiredTmpFiles extends Command
{
    protected $signature = 'system:clear-tmp
        {--hours=24 : 文件最后修改时间超过该小时数才删除}
        {--dry-run : 只统计不删除}';

    protected $description = 'Delete expired temporary files under storage/app/tmp (video transcode leftovers, thumbnails, etc.)';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = time() - ($hours * 3600);

        $tmpDir = storage_path('app/tmp');

        if (! is_dir($tmpDir)) {
            $this->info("Directory does not exist: {$tmpDir}. Nothing to clear.");

            return self::SUCCESS;
        }

        $deleted = 0;
        $failed = 0;
        $bytes = 0;

        // RecursiveIteratorIterator 直接遍历实际文件，跳过目录本身
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            // 正在处理的任务可能还在写入，只清超过保留期的文件
            if ($file->getMTime() > $cutoff) {
                continue;
            }

            $bytes += $file->getSize();

            if ($dryRun) {
                $deleted++;
                continue;
            }

            if (@unlink($file->getPathname())) {
                $deleted++;
            } else {
                $failed++;
                Log::warning("system:clear-tmp failed to delete: {$file->getPathname()}");
            }
        }

        $this->info(sprintf(
            'Expired tmp files [retention: %dh, deleted: %d (%.1f MB), failed: %d%s]',
            $hours,
            $deleted,
            $bytes / 1048576,
            $failed,
            $dryRun ? ', dry-run' : ''
        ));

        return self::SUCCESS;
    }
}
