<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Resources\User\Chat;

use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\User\UserDeletedResource;
use App\Http\Resources\User\User\UserPreviewResource;

class ChatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unreadMessagesCount = $this->resource->getUnreadMessagesCount();

        $chatItem = [
            'chat_id' => $this->chat_id,
            'is_group' => $this->type->isGroup(),
            'type' => $this->type->value,
            'unread_messages_count' => [
                'raw' => $unreadMessagesCount,
                'formatted' => Num::abbreviate($unreadMessagesCount)
            ],
            'last_activity' => [
                'time_ago' => $this->last_activity->getTimeAgo(),
                'raw' => $this->last_activity->getTimestamp(),
                'formatted' => $this->last_activity->getCalendar()
            ],
            'last_message' => null,
            'is_deleted' => false
        ];

        if ($this->type->isDirect()) {
            $interlocutor = $this->interlocutor;

            if(isset($interlocutor->user) && $interlocutor->user) {
                $chatItem['chat_info'] = [
                    'id' => $interlocutor->user->id,
                    'name' => $interlocutor->user->name,
                    'avatar_url' => $interlocutor->user->avatar_url,
                    'verified' => $interlocutor->user->isVerified(),
                ];
            }
            else {
                $chatItem['chat_info'] = [
                    'id' => 0,
                    'name' => 'Deleted Account',
                    'verified' => false,
                    'avatar_url' => asset(config('user.avatar'))
                ];
            }
        }
        else if ($this->type->isGroup()) {
            $chatItem['chat_info'] = [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'avatar_url' => $this->group->avatar_url,
                'verified' => $this->group->isVerified(),
            ];
        }

        if (! empty($this->lastMessage)) {
            if ($this->lastMessage->is_deleted) {
                $chatItem['is_deleted'] = true;
            }
            else {
                $chatItem['last_message'] = $this->lastMessage->content;
            }
        }

        return $chatItem;
    }
}
