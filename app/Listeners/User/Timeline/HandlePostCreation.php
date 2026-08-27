<?php

namespace App\Listeners\User\Timeline;

use App\Models\Post;

use App\Models\User;
use App\Services\Censor\CensorService;
use App\Events\User\Timeline\PostCreatedEvent;
use App\Jobs\User\Timeline\ConvertAndCompressPostAudio;
use App\Jobs\User\Timeline\ConvertAndCompressPostVideo;
use App\Notifications\User\Mention\PostMentionNotification;
use App\Services\Notification\NotificationBatcher;

class HandlePostCreation
{
    public function handle(PostCreatedEvent $event): void
    {
        if ($event->postData->type->isVideo()) {
            ConvertAndCompressPostVideo::dispatch($event->postData);
        }

        else if($event->postData->type->isAudio()) {
            ConvertAndCompressPostAudio::dispatch($event->postData);
        }

        $this->notifyMentionedUsers($event->postData);

        $this->censorPost($event->postData);
    }

    private function censorPost(Post $postData)
    {
        $censorService = app(CensorService::class);

        $censorService->setUser($postData->user)->censor($postData->content);
    }

    private function notifyMentionedUsers(Post $postData)
    {
        $mentions = $postData->getMentions();

        if ($mentions) {
            $mentionedUsers = User::active()->excludeSelf()->whereIn('username', $mentions)->get();

            $mentionedUsers->each(function($userData) use ($postData) {
                // 提及隐私：被@用户「谁能@我」设置不允许作者提及时，不发送提及通知
                if(! ($userData->permitSettings?->mentions->allows($postData->user, $userData) ?? true)) {
                    return;
                }

                // 聚合缓冲：提及并入 1h Digest 邮件
                NotificationBatcher::add(
                    $userData->id,
                    $postData->user_id,
                    'post',
                    $postData->id,
                    'post.mentioned'
                );

                $userData->notify(new PostMentionNotification($postData));
            });
        }
    }
}
