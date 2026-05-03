<?php

namespace App\Notifications\User\Chat;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;

class MessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function broadcastType(): string
    {
        return 'chat.notification';
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        // TODO: Add email channel with time window.

        if($notifiable->pushNotificationSettings->direct_messages) {
            array_push($channels, 'broadcast');
        }

        return $channels;
    }

    public function toBroadcast(): BroadcastMessage
    {
        return new BroadcastMessage([
            'data' => []
        ]);
    }
}
