<?php

namespace App\Jobs\User\Timeline;

use App\Models\Post;
use App\Models\Media;
use App\Enums\Media\MediaType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\System\Moderation\PostMarkedNsfwNotification;
use App\Services\Nsfw\NsfwDetectionService;

/**
 * 帖子媒体 NSFW 自动检测
 *
 * 图片/GIF 由 PostCreatedEvent（HandlePostCreation）触发；
 * 视频由转码完成的 MediaProcessedEvent（DetectPostNsfwOnMediaProcessed）触发，
 * 保证检测时媒体已 PROCESSED 且文件在最终磁盘。
 *
 * 命中后自动标记 post.is_sensitive 并通知作者；检测服务异常直接抛出交队列
 * 重试，绝不因失败标记敏感。用户已手动标记（is_sensitive=true）的帖子跳过。
 */
class DetectPostNsfwContent implements ShouldQueue
{
    use Queueable;

    public $timeout = 120;

    private $postData;

    public function __construct(Post $postData)
    {
        $this->postData = $postData;
    }

    public function handle(NsfwDetectionService $detectionService): void
    {
        if (! config('features.nsfw_detection.enabled')) {
            return;
        }

        // 队列序列化后重新查询，帖子可能已被删除
        $post = Post::find($this->postData->id);

        if (! $post || $post->is_sensitive) {
            return;
        }

        $flagged = false;

        $post->media()
            ->whereIn('type', [MediaType::IMAGE, MediaType::GIF, MediaType::VIDEO])
            ->where('status', 'processed')
            ->get()
            ->each(function (Media $media) use ($detectionService, &$flagged) {
                $result = $this->detectMedia($detectionService, $media);

                if ($result === null) {
                    return; // 文件不可得，跳过该媒体
                }

                if ($result) {
                    $flagged = true;
                }
            });

        if ($flagged) {
            $post->is_sensitive = true;
            $post->save();

            if (config('features.nsfw_detection.notify_author', true)) {
                $post->user?->notify(new PostMarkedNsfwNotification($post));
            }

            Log::info("NSFW detection flagged post {$post->id} as sensitive content.");
        }
    }

    /**
     * 检测单个媒体并写入 metadata，返回是否命中（null = 文件不可得已跳过）
     */
    private function detectMedia(NsfwDetectionService $detectionService, Media $media): ?bool
    {
        $localPath = null;
        $isTemporary = false;

        try {
            $localPath = $this->resolveMediaPath($media);

            if ($localPath === null) {
                return null;
            }

            $isTemporary = $localPath['temporary'];
            $absPath = $localPath['path'];

            $detections = $media->type->isVideo()
                ? $detectionService->detectVideo($absPath)
                : $detectionService->detectImage($absPath);
        } finally {
            if ($isTemporary && isset($absPath) && is_file($absPath)) {
                @unlink($absPath);
            }
        }

        $hits = $detectionService->evaluate($detections);
        $flagged = ! empty($hits);

        $metadata = $media->metadata ?? [];
        $metadata['nsfw_detection'] = [
            'flagged' => $flagged,
            'labels' => array_column($hits, 'label'),
            'max_score' => $hits ? max(array_column($hits, 'score')) : null,
            'checked_at' => now()->toIso8601String(),
            'engine' => 'nudenet',
        ];

        $media->metadata = $metadata;
        $media->save();

        return $flagged;
    }

    /**
     * 取媒体本地绝对路径：本地盘直接取 path()，远端盘下载到 tmp/nsfw（调用方负责清理）
     *
     * @return null|array{path: string, temporary: bool}
     */
    private function resolveMediaPath(Media $media): ?array
    {
        $diskName = $media->disk;
        $disk = Storage::disk($diskName);

        if (! $disk->exists($media->source_path)) {
            Log::warning("NSFW detection skipped media {$media->id}: source file not found on disk [{$diskName}].");

            return null;
        }

        // 本地盘直接引用绝对路径，不复制文件
        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            return ['path' => $disk->path($media->source_path), 'temporary' => false];
        }

        // 远端盘（s3 等）流式下载到本地临时目录
        $tmpDir = storage_path('app/tmp/nsfw');

        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $tmpPath = $tmpDir . '/' . uniqid('nsfw_') . '_' . basename($media->source_path);

        $stream = $disk->readStream($media->source_path);

        if (! $stream) {
            Log::warning("NSFW detection skipped media {$media->id}: unable to read from disk [{$diskName}].");

            return null;
        }

        $target = fopen($tmpPath, 'wb');

        while (! feof($stream)) {
            fwrite($target, fread($stream, 4096));
        }

        fclose($stream);
        fclose($target);

        return ['path' => $tmpPath, 'temporary' => true];
    }

    public function tries(): int
    {
        return 3;
    }

    public function backoff(): array
    {
        return [60, 300];
    }
}
