<?php

namespace App\Enums\Chat;

enum MessageType: string
{
    case TEXT = 'text';
    case AUDIO = 'audio';
    case IMAGE = 'image';
    case VIDEO_CIRCLE = 'video_circle';

    public function isText():bool
    {
        return $this == self::TEXT;
    }

    public function isVideo():bool
    {
        return $this == self::VIDEO;
    }

    public function isVideoCircle():bool
    {
        return $this == self::VIDEO_CIRCLE;
    }

    public function isImage():bool
    {
        return $this == self::IMAGE;
    }
}
