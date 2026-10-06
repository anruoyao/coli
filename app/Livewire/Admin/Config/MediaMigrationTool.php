<?php

namespace App\Livewire\Admin\Config;

use App\Models\MediaMigration;
use App\Services\MediaMigration\MediaMigrationService;
use App\Enums\Media\MediaMigrationStatus;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * 媒体文件迁移工具（后台「存储」分组）。
 *
 * 两个核心场景：
 * 1. 本地迁移（换服务器）：磁盘打包 zip → 下载 → 新服务器分片上传 → 解压恢复 → 完整性校验
 * 2. 磁盘迁移（对接 S3 后）：一键把本地磁盘全部媒体迁移到 S3 磁盘，
 *    同步更新 media 表磁盘引用与 data_stats 用量统计，支持断点续传与失败重试。
 */
class MediaMigrationTool extends Component
{
    // 打包磁盘（本地迁移）
    public string $archiveDisk = '';

    // 解压目标磁盘
    public string $extractDisk = '';

    // 磁盘迁移：源 / 目标
    public string $sourceDisk = '';

    public string $targetDisk = '';

    public bool $checksumVerify = false;

    // 主存储磁盘切换
    public string $staticDiskChoice = '';

    // 报告查看
    public ?int $reportMigrationId = null;

    public ?string $flashContent = null;

    public string $flashType = 'success';

    public function mount(): void
    {
        $disks = app(MediaMigrationService::class)->getMigrationDisks();
        $diskIds = array_column($disks, 'id');

        $this->archiveDisk = $diskIds[0] ?? '';
        $this->extractDisk = $diskIds[0] ?? '';
        $this->sourceDisk = $diskIds[0] ?? '';
        $this->staticDiskChoice = static_storage_disk();

        // 默认目标磁盘：优先 S3，其次任意非源磁盘
        $s3Disks = array_values(array_filter($diskIds, fn ($id) => $id !== $this->sourceDisk
            && (config("filesystems.disks.{$id}.driver") === 's3')));

        if (! empty($s3Disks)) {
            $this->targetDisk = $s3Disks[0];
        }
        else {
            $others = array_values(array_diff($diskIds, [$this->sourceDisk]));
            $this->targetDisk = $others[0] ?? '';
        }
    }

    #[On('media-archive-uploaded')]
    public function archiveUploaded(string $name = ''): void
    {
        $this->flash(__('admin/media-migration.flash.archive_uploaded', ['name' => $name]), 'success');
    }

    /**
     * 场景一：创建本地磁盘压缩包
     */
    public function createArchive(): void
    {
        if ($this->hasRunningMigration()) {
            $this->flash(__('admin/media-migration.flash.migration_running'), 'error');

            return;
        }

        try {
            app(MediaMigrationService::class)->startArchive($this->archiveDisk);

            $this->flash(__('admin/media-migration.flash.archive_started'), 'success');
        } catch (Throwable $th) {
            $this->flash($th->getMessage(), 'error');
        }
    }

    /**
     * 场景一：解压恢复压缩包
     */
    public function extractArchive(string $name): void
    {
        if ($this->hasRunningMigration()) {
            $this->flash(__('admin/media-migration.flash.migration_running'), 'error');

            return;
        }

        try {
            app(MediaMigrationService::class)->startExtract($name, $this->extractDisk);

            $this->flash(__('admin/media-migration.flash.extract_started'), 'success');
        } catch (Throwable $th) {
            $this->flash($th->getMessage(), 'error');
        }
    }

    /**
     * 场景二：磁盘间迁移（本地 → S3）
     */
    public function startTransfer(): void
    {
        if ($this->hasRunningMigration()) {
            $this->flash(__('admin/media-migration.flash.migration_running'), 'error');

            return;
        }

        try {
            app(MediaMigrationService::class)->startDiskTransfer(
                $this->sourceDisk,
                $this->targetDisk,
                $this->checksumVerify
            );

            $this->flash(__('admin/media-migration.flash.transfer_started'), 'success');
        } catch (Throwable $th) {
            $this->flash($th->getMessage(), 'error');
        }
    }

    /**
     * 恢复中断的迁移（断点续传入口）
     */
    public function resumeMigration(int $id): void
    {
        $migration = MediaMigration::find($id);

        if (! $migration) {
            return;
        }

        if ($migration->status === MediaMigrationStatus::FAILED) {
            try {
                app(MediaMigrationService::class)->startDiskTransfer(
                    $migration->source_disk,
                    $migration->target_disk,
                    boolval($migration->options['checksum'] ?? false)
                );

                $this->flash(__('admin/media-migration.flash.resumed'), 'success');
            } catch (Throwable $th) {
                $this->flash($th->getMessage(), 'error');
            }
        }
    }

    public function cancelMigration(int $id): void
    {
        try {
            $migration = MediaMigration::find($id);

            if ($migration) {
                app(MediaMigrationService::class)->cancel($migration);

                $this->flash(__('admin/media-migration.flash.cancelled'), 'success');
            }
        } catch (Throwable $th) {
            $this->flash($th->getMessage(), 'error');
        }
    }

    /**
     * 重试迁移中失败的文件（磁盘迁移场景）
     */
    public function retryFailed(int $id): void
    {
        if ($this->hasRunningMigration()) {
            $this->flash(__('admin/media-migration.flash.migration_running'), 'error');

            return;
        }

        try {
            app(MediaMigrationService::class)->retryFailed(MediaMigration::findOrFail($id));

            $this->flash(__('admin/media-migration.flash.retry_started'), 'success');
        } catch (Throwable $th) {
            $this->flash($th->getMessage(), 'error');
        }
    }

    /**
     * 切换主存储磁盘（磁盘迁移后必须执行，否则头像等静态资源仍指向旧磁盘）
     */
    public function switchStaticDisk(): void
    {
        if (empty($this->staticDiskChoice) || $this->staticDiskChoice === static_storage_disk()) {
            return;
        }

        try {
            $switched = app(MediaMigrationService::class)->switchStaticDisk($this->staticDiskChoice);

            if (! $switched) {
                $this->flash(__('admin/media-migration.flash.env_not_writable'), 'error');

                return;
            }

            defer(function () {
                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                Artisan::call('optimize:clear');

                if (app()->isProduction()) {
                    Artisan::call('optimize');
                }

                // 队列工作进程持有旧的静态磁盘配置，需要重启
                try {
                    Artisan::call('horizon:terminate');
                } catch (Throwable $th) {
                    // Horizon 未部署时忽略
                }
            });

            $this->flash(__('admin/media-migration.flash.static_disk_switched', ['disk' => $this->staticDiskChoice]), 'success');
        } catch (Throwable $th) {
            $this->flash($th->getMessage(), 'error');
        }
    }

    public function deleteArchive(string $name): void
    {
        try {
            $name = basename($name);
            $service = app(MediaMigrationService::class);

            $service->getWorkDisk()->delete($service->archivesDir().'/'.$name);

            $this->flash(__('admin/media-migration.flash.archive_deleted'), 'success');
        } catch (Throwable $th) {
            $this->flash($th->getMessage(), 'error');
        }
    }

    public function downloadArchive(string $name)
    {
        $name = basename($name);
        $service = app(MediaMigrationService::class);

        $path = $service->archivesDir().'/'.$name;
        $absPath = $service->getWorkDisk()->path($path);

        if (! $service->getWorkDisk()->exists($path)) {
            $this->flash(__('admin/media-migration.flash.archive_not_found'), 'error');

            return null;
        }

        return response()->streamDownload(function () use ($absPath) {
            $stream = fopen($absPath, 'rb');
            fpassthru($stream);
            fclose($stream);
        }, $name, [
            'Content-Type' => 'application/zip',
            'Content-Length' => filesize($absPath),
        ]);
    }

    public function downloadReport(int $id)
    {
        $migration = MediaMigration::find($id);

        if (! $migration) {
            return null;
        }

        $report = $migration->report ?? [];

        $content = json_encode([
            'migration' => [
                'id' => $migration->id,
                'type' => $migration->type->value,
                'status' => $migration->status->value,
                'source_disk' => $migration->source_disk,
                'target_disk' => $migration->target_disk,
                'archive_path' => $migration->archive_path,
                'started_at' => optional($migration->started_at)->toDateTimeString(),
                'finished_at' => optional($migration->finished_at)->toDateTimeString(),
                'error' => $migration->error,
            ],
            'report' => $report,
            'failures' => app(MediaMigrationService::class)->readFailures($migration->id, 10000),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, "media-migration-{$migration->id}-report.json", [
            'Content-Type' => 'application/json',
        ]);
    }

    public function showReport(int $id): void
    {
        $this->reportMigrationId = ($this->reportMigrationId === $id) ? null : $id;
    }

    public function refreshStatus(): void
    {
        // wire:poll 触发的刷新（render 会重新读取状态）
    }

    public function render()
    {
        $service = app(MediaMigrationService::class);

        $activeMigration = MediaMigration::where('status', MediaMigrationStatus::RUNNING)->latest('id')->first();

        $history = MediaMigration::latest('id')->limit(15)->get();

        $reportMigration = $this->reportMigrationId
            ? MediaMigration::find($this->reportMigrationId)
            : null;

        return view('livewire.admin.config.media-migration-tool', [
            'disks' => $service->getMigrationDisks(),
            'activeMigration' => $activeMigration,
            'history' => $history,
            'archives' => $service->listArchives(),
            'reportMigration' => $reportMigration,
            'staticDisk' => static_storage_disk(),
        ]);
    }

    private function hasRunningMigration(): bool
    {
        return MediaMigration::where('status', MediaMigrationStatus::RUNNING)->exists();
    }

    private function flash(string $content, string $type = 'success'): void
    {
        $this->flashContent = $content;
        $this->flashType = $type;
    }
}
