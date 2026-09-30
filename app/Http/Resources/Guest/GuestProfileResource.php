<?php

namespace App\Http\Resources\Guest;

use App\Constants\Relationship;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 访客版用户主页（白名单序列化）。
 *
 * 结构与登录态 ProfileResource 对齐（现有 profile 子组件可复用），
 * 但所有值均为访客安全值：权限全 false、关系全中性、隐私字段全部不输出。
 *
 * 严禁包含：email/phone/website 实际值/社交链接/位置/生日/last_active/关系状态。
 */
class GuestProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'caption' => empty($this->caption) ? null : $this->caption,
            'avatar_url' => $this->avatar_url,
            'cover_url' => $this->cover_url,
            'profile_url' => $this->profile_url,
            'bio' => $this->bio,
            'join_date' => [
                'raw' => $this->created_at?->getTimestamp(),
                'formatted' => $this->getCreatedAt()->getCalendar(),
            ],
            'gender' => null,
            'verified' => $this->isVerified(),
            'publications_count' => [
                'raw' => $this->publications_count,
                'formatted' => Num::abbreviate($this->publications_count),
            ],
            'followers_count' => [
                'raw' => $this->followers_count,
                'formatted' => Num::abbreviate($this->followers_count),
            ],
            'following_count' => [
                'raw' => $this->followings_count,
                'formatted' => Num::abbreviate($this->followings_count),
            ],
            'meta' => [
                'is_owner' => false,
                'permissions' => [
                    'can_follow' => false,
                    'can_message' => false,
                    'can_edit' => false,
                    'can_view_followers' => false,
                    'can_view_followings' => false,
                    'can_sanction' => false,
                    'can_mention' => false,
                    'can_story_reply' => false,
                    'can_block' => false,
                    'can_report' => false,
                    'can_mute' => false,
                ],
                'relationship' => [
                    Relationship::FOLLOW_GROUP => [
                        Relationship::FOLLOWING => false,
                        Relationship::FOLLOWED_BY => false,
                        Relationship::REQUESTED => false,
                    ],
                    Relationship::BLOCK_GROUP => [
                        Relationship::BLOCKING => false,
                        Relationship::BLOCKED_BY => false,
                    ],
                    Relationship::MUTING_GROUP => [
                        Relationship::MUTING => false,
                    ],
                ],
            ],
        ];
    }
}
