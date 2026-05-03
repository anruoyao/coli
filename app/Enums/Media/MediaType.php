<?php

namespace App\Enums\Media;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case GIF = 'gif';

    public function isImage():bool
    {
        return $this == self::IMAGE;
    }

    public function isVideo():bool
    {
        return $this == self::VIDEO;
    }

    public function isAudio():bool
    {
        return $this == self::AUDIO;
    }

    public function isDocument():bool
    {
        return $this == self::DOCUMENT;
    }

    public function isGif():bool
    {
        return $this == self::GIF;
    }
}
