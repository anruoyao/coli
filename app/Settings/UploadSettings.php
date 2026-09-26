<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * 媒体上传大小限制配置（后台可调）。
 *
 * 单位：MB。合并进 config('upload.*.max_mb')，同时以 KB 形式合并进
 * config('upload.*.max') 供 Laravel max 校验规则与客户端限制接口使用。
 */
class UploadSettings extends Settings
{
    public int $image_max_mb;
    public int $video_max_mb;
    public int $audio_max_mb;
    public int $gif_max_mb;
    public int $document_max_mb;

    public static function group(): string
    {
        return 'upload';
    }
}
