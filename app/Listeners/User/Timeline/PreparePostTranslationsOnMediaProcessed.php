<?php

namespace App\Listeners\User\Timeline;

use App\Enums\Media\MediaStatus;
use App\Enums\Post\PostStatus;
use App\Events\User\Timeline\MediaProcessedEvent;
use App\Jobs\User\Translation\PreparePostTranslations;

class PreparePostTranslationsOnMediaProcessed
{
    public function handle(MediaProcessedEvent $event): void
    {
        $media = $event->getMedia();
        if (! in_array($media->type?->value, ['video', 'audio'], true) || $media->status !== MediaStatus::PROCESSED) {
            return;
        }

        $post = $media->post;
        if ($post?->exists && $post->status === PostStatus::ACTIVE) {
            PreparePostTranslations::dispatch($post->id)->afterCommit();
        }
    }
}
