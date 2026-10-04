<?php

namespace App\Listeners\User\Timeline;

use App\Jobs\User\Timeline\DetectPostNsfwContent;
use App\Events\User\Timeline\MediaProcessedEvent;

/**
 * 视频转码完成后触发 NSFW 检测
 *
 * MediaProcessedEvent 由视频/音频转码 Job 在媒体 PROCESSED 后广播，
 * 此处仅处理视频：转码完成后源文件已落最终磁盘，检测避开转码期 tmp 并发。
 */
class DetectPostNsfwOnMediaProcessed
{
    public function handle(MediaProcessedEvent $event): void
    {
        if (! config('features.nsfw_detection.enabled')) {
            return;
        }

        $media = $event->getMedia();

        if (! $media->type->isVideo()) {
            return; // 音频不涉及 NSFW 检测
        }

        // MediaProcessedEvent 仅由帖子视频/音频转码 Job 触发，媒体必属 Post。
        // 注意：勿用 $media->mediaable（Media 模型该 morphTo 定义参数有误，生产未使用）。
        $post = $media->post;

        if ($post && $post->exists) {
            DetectPostNsfwContent::dispatch($post);
        }
    }
}
