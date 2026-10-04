<?php

namespace App\Notifications\System\Moderation;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use App\Constants\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Traits\HasSystemActor;
use App\Notifications\Traits\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 帖子被系统自动标记为敏感内容（NSFW 检测命中）
 *
 * 系统通知（无用户 actor），走 important 通道：database + mail + broadcast。
 */
class PostMarkedNsfwNotification extends Notification implements ShouldQueue
{
    use Queueable,
        HasSystemActor,
        BaseNotification;

    public array $actorData;

    public $notificationType = Notifications::POST_MARKED_NSFW;

    private Post $postData;

    public function __construct(Post $postData)
    {
        $this->actorData = $this->getSystemActor();
        $this->postData = $postData;
    }

    public function via(object $notifiable): array
    {
        return $this->getImportantNotificationChannels();
    }

    public function toPush(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())->subject(__('notifications.subjects.post_marked_nsfw', locale: $notifiable->language))->view($this->notificationViewPath, [
            'notifiable' => $notifiable,
            'data' => $this->getData(),
            'notificationType' => $this->notificationType,
            'destinationLink' => $this->getDestinationLink(),
            'locale' => $notifiable->language
        ]);
    }

    public function toDatabase(): array
    {
        return $this->getData();
    }

    private function getData()
    {
        return [
            'message_group' => 'important',
            'message_key' => 'post_marked_nsfw',
            'message_params' => [
                'content' => $this->cutContent($this->postData->content)
            ],
            'metadata' => [
                'is_viewable' => true
            ],
            'entity' => [
                'id' => $this->postData->id,
                'hash_id' => $this->postData->hashId,
                'content' => $this->cutContent($this->postData->content)
            ],
            'actor' => $this->actorData
        ];
    }

    private function getDestinationLink(): string
    {
        return url("/publication/{$this->postData->hashId}");
    }
}
