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

namespace App\Http\Resources\User\Profile;

use Carbon\Carbon;
use App\Support\Num;
use Illuminate\Http\Request;
use App\Constants\Relationship;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isMe = ($this->id == me()->id);

        $profileData = [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->first_name,
            'username' => $this->username,
            'caption' => $this->caption,
            'avatar_url' => $this->avatar_url,
            'cover_url' => $this->cover_url,
            'profile_url' => $this->profile_url,
            'category' => $this->category,
            'bio' => $this->bio,
            'join_date' => [
                'raw' => $this->getCreatedAt()->getTimestamp(),
                'formatted' => $this->getCreatedAt()->getCalendar()
            ],
            'gender' => $this->gender,
            'website' => $this->website,
            'verified' => $this->verified,
            'publications_count' => [
                'raw' => $this->publications_count,
                'formatted' => Num::abbreviate($this->publications_count)
            ],
            'followers_count' => [
                'raw' => $this->followers_count,
                'formatted' => Num::abbreviate($this->followers_count)
            ],
            'following_count' => [
                'raw' => $this->following_count,
                'formatted' => Num::abbreviate($this->following_count)
            ],
            'meta' => [
                'is_owner' => $isMe,
                'permissions' => [
                    'can_sanction' => (! $isMe && me()->isAdmin()),
                    'can_follow' => $this->canFollow(me()),
                    'can_mention' => (! $isMe),
                    'can_message' => (! $isMe),
                    'can_block' => (! $isMe),
                    'can_report' => (! $isMe),
                    'can_mute' => (! $isMe),
                ],
                'relationship' => [
                    Relationship::FOLLOW_GROUP => [
                        Relationship::FOLLOWING => me()->isFollowing($this->resource),
                        Relationship::FOLLOWED_BY => $this->isFollowing(me()),
                        Relationship::REQUESTED_BY => false,
                        Relationship::REQUESTED => me()->followRequested($this->resource)
                    ],
                    Relationship::BLOCK_GROUP => [
                        Relationship::BLOCKING => false,
                        Relationship::BLOCKED_BY => false
                    ],
                    Relationship::MUTING_GROUP => [
                        Relationship::MUTING => false,
                        Relationship::MUTING_NOTIFICATIONS => false
                    ]
                ]
            ]
        ];

        if(empty($this->privacySettings->country_privacy)) {
            $profileData['country'] = $this->country;
            $profileData['country_name'] = $this->country_name;
        }

        if(empty($this->privacySettings->city_privacy)) {
            $profileData['city'] = $this->city;
        }

        return $profileData;
    }
}
