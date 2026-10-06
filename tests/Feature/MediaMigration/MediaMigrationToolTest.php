<?php

namespace Tests\Feature\MediaMigration;

use Tests\TestCase;
use App\Models\Media;
use App\Models\DataStat;
use App\Models\MediaMigration;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaMigrationStatus;
use App\Enums\Media\MediaMigrationType;
use App\Jobs\MediaMigration\MigrateDiskFilesJob;
use App\Services\MediaMigration\MediaMigrationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 媒体文件迁移工具功能测试。
 *
 * 覆盖：
 * - 本地打包 → 解压恢复 → 校验和验证的完整往返（换服务器场景）
 * - 磁盘迁移：文件复制 + media 表磁盘引用更新 + data_stats 用量归并
 * - 失败记录 / 断点续传 / 失败重试
 *
 * 注：使用 DatabaseTransactions；磁盘用 Storage::fake 隔离；
 *     目标磁盘用本地驱动模拟 S3（迁移逻辑与驱动无关）。
 */
class MediaMigrationToolTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.app_key.enabled' => false]);

        Storage::fake('public');
        Storage::fake('local');

        // 目标磁盘：本地驱动模拟（磁盘迁移逻辑与驱动无关）
        config(['filesystems.disks.migration_target' => [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/migration_target'),
            'throw' => false,
        ]]);

        Storage::fake('migration_target');
    }

    /**
     * 场景一：本地打包 → 新服务器解压恢复，内容逐文件校验一致
     */
    public function test_archive_and_extract_roundtrip(): void
    {
        $service = app(MediaMigrationService::class);

        $this->seedMediaFiles('public');

        $migration = $service->startArchive('public');

        $migration->refresh();

        $this->assertTrue($migration->status->isCompleted(), 'Archive migration should complete (sync queue).');
        $this->assertSame(3, (int) $migration->files_total);
        $this->assertSame(3, (int) $migration->files_done);
        $this->assertSame(0, (int) $migration->files_failed);
        $this->assertNotEmpty($migration->report['archive_name']);

        // 压缩包已生成且可下载
        $archives = $service->listArchives();

        $this->assertCount(1, $archives);

        // 「新服务器」：解压到目标磁盘（此前为空）
        $extract = $service->startExtract($archives[0]['name'], 'migration_target');

        $extract->refresh();

        $this->assertTrue($extract->status->isCompleted());
        $this->assertSame(3, (int) $extract->files_done);
        $this->assertSame(0, (int) $extract->verify_missing, 'No files should be missing after extraction.');
        $this->assertSame(0, (int) $extract->verify_mismatch, 'No checksum mismatches expected.');

        // 内容一致（含子目录结构）
        $this->assertSame('image-a-content', Storage::disk('migration_target')->get('uploads/images/a.webp'));
        $this->assertSame('image-b-content', Storage::disk('migration_target')->get('uploads/images/b.webp'));
        $this->assertSame('thumb-content', Storage::disk('migration_target')->get('uploads/videos/thumb.jpg'));
    }

    /**
     * 场景二：磁盘迁移——文件复制、数据库引用更新、用量统计归并
     */
    public function test_disk_transfer_copies_files_updates_database_and_stats(): void
    {
        $service = app(MediaMigrationService::class);

        $media = $this->seedMediaFiles('public');

        $migration = $service->startDiskTransfer('public', 'migration_target', true);

        $migration->refresh();

        $this->assertTrue($migration->status->isCompleted());
        $this->assertSame(3, (int) $migration->files_done);
        $this->assertSame(0, (int) $migration->files_failed);
        $this->assertGreaterThan(0, (int) ($migration->report['db_rows_updated'] ?? 0));

        // 文件已复制（含 checksum 校验通过）
        $this->assertTrue(Storage::disk('migration_target')->exists('uploads/images/a.webp'));
        $this->assertSame('image-b-content', Storage::disk('migration_target')->get('uploads/images/b.webp'));
        $this->assertTrue(Storage::disk('migration_target')->exists('uploads/videos/thumb.jpg'));

        // 源文件保留（迁移是复制而非移动，回滚安全）
        $this->assertTrue(Storage::disk('public')->exists('uploads/images/a.webp'));

        // 数据库引用已更新：source 与 thumbnail 两个维度
        $this->assertDatabaseHas('media', [
            'source_path' => 'uploads/images/a.webp',
            'disk' => 'migration_target',
        ]);

        $this->assertDatabaseHas('media', [
            'source_path' => 'uploads/images/b.webp',
            'disk' => 'migration_target',
            'thumbnail_path' => 'uploads/videos/thumb.jpg',
            'thumbnail_disk' => 'migration_target',
        ]);

        // 用量统计从源磁盘归并到目标磁盘
        $this->assertDatabaseHas('data_stats', [
            'disk' => 'migration_target',
            'media_type' => 'image',
            'content_items' => 2,
        ]);

        $this->assertDatabaseMissing('data_stats', ['disk' => 'public']);
    }

    /**
     * 失败记录 + 断点续传语义 + 失败文件重试
     */
    public function test_disk_transfer_failure_recording_and_retry(): void
    {
        $service = app(MediaMigrationService::class);

        $this->seedMediaFiles('public');

        // 手动构建迁移（不经 startDiskTransfer，以便在任务执行前破坏一个源文件）
        $migration = MediaMigration::create([
            'type' => MediaMigrationType::DISK_TRANSFER,
            'status' => MediaMigrationStatus::RUNNING,
            'source_disk' => 'public',
            'target_disk' => 'migration_target',
            'options' => ['checksum' => false],
            'started_at' => now(),
        ]);

        $service->buildManifestForDisk($migration, 'public');

        // 迁移执行前删除一个源文件 → 该文件复制失败，其余成功
        Storage::disk('public')->delete('uploads/images/a.webp');

        // 直接执行任务（sync 队列下会自链式执行到结束）
        (new MigrateDiskFilesJob($migration->id))->handle($service);

        $migration->refresh();

        $this->assertTrue($migration->status->isCompleted(), 'Migration completes even with per-file failures.');
        $this->assertSame(1, (int) $migration->files_failed);
        $this->assertSame(2, (int) $migration->files_done);

        // 失败文件记录在报告中
        $this->assertNotEmpty($migration->report['failures_sample']);
        $this->assertSame('uploads/images/a.webp', $migration->report['failures_sample'][0]['p']);

        // 失败文件的数据库引用保持源磁盘
        $this->assertDatabaseHas('media', [
            'source_path' => 'uploads/images/a.webp',
            'disk' => 'public',
        ]);

        // 恢复源文件后重试失败项
        Storage::disk('public')->put('uploads/images/a.webp', 'image-a-restored');

        $retry = $service->retryFailed($migration);

        $retry->refresh();

        $this->assertTrue($retry->status->isCompleted());
        $this->assertSame(0, (int) $retry->files_failed);
        $this->assertSame(1, (int) $retry->files_done);

        $this->assertDatabaseHas('media', [
            'source_path' => 'uploads/images/a.webp',
            'disk' => 'migration_target',
        ]);

        $this->assertSame('image-a-restored', Storage::disk('migration_target')->get('uploads/images/a.webp'));
    }

    /**
     * 中断迁移可从断点继续（模拟进程中断后重新派发）
     */
    public function test_interrupted_migration_resumes_from_cursor(): void
    {
        $service = app(MediaMigrationService::class);

        $this->seedMediaFiles('public');

        $migration = MediaMigration::create([
            'type' => MediaMigrationType::DISK_TRANSFER,
            'status' => MediaMigrationStatus::RUNNING,
            'source_disk' => 'public',
            'target_disk' => 'migration_target',
            'options' => ['checksum' => false],
            'started_at' => now(),
        ]);

        $service->buildManifestForDisk($migration, 'public');

        // 模拟执行到一半被中断：只处理前两个文件
        // （忠实模拟任务行为：复制文件 + 更新数据库引用，然后游标落库）
        $slice = iterator_to_array($service->readManifestSlice($migration->id, 0, 2));

        $this->assertCount(2, $slice);

        foreach ($slice as $entry) {
            Storage::disk('migration_target')->writeStream(
                $entry->p,
                Storage::disk('public')->readStream($entry->p)
            );
        }

        $service->updateDatabaseReferences('public', 'migration_target', array_column($slice, 'p'));

        $migration->cursor = 2;
        $migration->files_done = 2;
        $migration->bytes_done = 42;
        $migration->save();

        // 中断后重新派发：从游标继续，不会重复也不会遗漏
        (new MigrateDiskFilesJob($migration->id))->handle($service);

        $migration->refresh();

        $this->assertTrue($migration->status->isCompleted());
        $this->assertSame(3, (int) $migration->files_done);
        $this->assertSame(0, (int) $migration->files_failed);

        // 数据库引用全部更新
        $this->assertSame(0, Media::where('disk', 'public')->count());
        $this->assertSame(2, Media::where('disk', 'migration_target')->count());
    }

    /**
     * 迁移列表与磁盘可用性
     */
    public function test_migration_disks_listing_excludes_system_disks(): void
    {
        $service = app(MediaMigrationService::class);

        $diskIds = collect($service->getMigrationDisks())->pluck('id')->all();

        $this->assertContains('public', $diskIds);
        $this->assertContains('migration_target', $diskIds);

        foreach (array_keys(config('filesystems.system_disks')) as $systemDisk) {
            $this->assertNotContains($systemDisk, $diskIds);
        }
    }

    /**
     * 后台 Livewire 组件可正常渲染（捕获视图/语言键错误）
     */
    public function test_livewire_component_renders(): void
    {
        \Livewire\Livewire::test(\App\Livewire\Admin\Config\MediaMigrationTool::class)
            ->assertHasNoErrors();
    }

    /**
     * 写入测试媒体文件与数据库记录
     */
    private function seedMediaFiles(string $disk): array
    {
        Storage::disk($disk)->put('uploads/images/a.webp', 'image-a-content');
        Storage::disk($disk)->put('uploads/images/b.webp', 'image-b-content');
        Storage::disk($disk)->put('uploads/videos/thumb.jpg', 'thumb-content');

        $first = Media::create([
            'mediaable_id' => 1,
            'mediaable_type' => 'post',
            'source_path' => 'uploads/images/a.webp',
            'disk' => $disk,
            'type' => MediaType::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'extension' => 'webp',
            'mime' => 'image/webp',
            'size' => strlen('image-a-content'),
        ]);

        $second = Media::create([
            'mediaable_id' => 2,
            'mediaable_type' => 'post',
            'source_path' => 'uploads/images/b.webp',
            'thumbnail_path' => 'uploads/videos/thumb.jpg',
            'disk' => $disk,
            'thumbnail_disk' => $disk,
            'type' => MediaType::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'extension' => 'webp',
            'mime' => 'image/webp',
            'size' => strlen('image-b-content'),
        ]);

        return [$first, $second];
    }
}
