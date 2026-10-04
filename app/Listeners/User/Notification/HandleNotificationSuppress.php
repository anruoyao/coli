<?php

namespace App\Listeners\User\Notification;

use App\Services\Relations\MuteService;
use Illuminate\Notifications\Events\NotificationSending;

class HandleNotificationSuppress
{
    public function handle(NotificationSending $event): bool
    {
        $notification = $event->notification;
        $notifiable   = $event->notifiable;

        if (property_exists($notification, 'actorData')) {
            $actorData = $notification->actorData;

            // 系统 actor（HasSystemActor，id=0）无对应用户，跳过静音检查，
            // 否则 MuteService 构造时 User::activeById(0) 为 null 直接 TypeError。
            if (($actorData['id'] ?? 0) !== 0) {
                $muteService = new MuteService($notifiable->id, $actorData['id']);

                if ($muteService->isMuted()) {
                    return false;
                }
            }
        }

        return true;
    }
}
