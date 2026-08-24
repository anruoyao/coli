<?php

namespace App\Services\Relations;

use App\Enums\User\FollowStatus;
use App\Models\{Follow, User};
use Exception;

class FollowService
{
    private User $followerData;
    private User $followingData;

    /**
     * @param int|User $follower
     * @param int|User $following
     * @throws Exception
     * @return void
     *
     * You can pass either the user ID or the User model instance.
     */

    public function __construct(int|User $follower, int|User $following)
    {
        $this->followerData = $follower instanceof User ? $follower : User::activeById($follower)->first();
        $this->followingData = $following instanceof User ? $following : User::activeById($following)->first();

        if(! $this->followerData || ! $this->followingData) {
            throw new Exception('Follower or following user not found.');
        }
    }

    public function isFollowing(): bool
    {
        return Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::FOLLOWING
        ])->exists();
    }

    public function follow(): Follow
    {
        // 已存在任何状态的关注/请求记录时直接复用，避免产生重复行
        $existing = Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id
        ])->first();

        if($existing) {
            return $existing;
        }

        if($this->followingData->permitSettings->followers->onlyApproved()) {
            return Follow::create([
                'follower_id' => $this->followerData->id,
                'following_id' => $this->followingData->id,
                'status' => FollowStatus::REQUESTED
            ]);
        }
        else {
            $this->followingData->increment('followers_count', 1);
            $this->followerData->increment('following_count', 1);

            return Follow::create([
                'follower_id' => $this->followerData->id,
                'following_id' => $this->followingData->id,
                'status' => FollowStatus::FOLLOWING
            ]);
        }
    }

    public function accept(): void
    {
        $follow = Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::REQUESTED
        ])->first();

        if($follow) {
            $follow->update([
                'status' => FollowStatus::FOLLOWING
            ]);

            $this->followingData->increment('followers_count', 1);
            $this->followerData->increment('following_count', 1);
        }
    }

    /**
     * 拒绝关注请求：删除 REQUESTED 记录（计数不变，请求阶段未计数）。
     */
    public function decline(): void
    {
        Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::REQUESTED
        ])->delete();
    }

    public function unfollow(): void
    {
        $deleted = Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::FOLLOWING
        ])->delete();

        // 仅删除 FOLLOWING 记录时才回退计数；取消的是关注请求则不涉及计数
        if($deleted) {
            User::where('id', $this->followingData->id)->where('followers_count', '>', 0)->decrement('followers_count');
            User::where('id', $this->followerData->id)->where('following_count', '>', 0)->decrement('following_count');
        }

        // 同时清理挂起中的关注请求（取消请求）
        Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::REQUESTED
        ])->delete();
    }

    public function followRequested(): bool
    {
        return Follow::where([
            'follower_id' => $this->followerData->id,
            'following_id' => $this->followingData->id,
            'status' => FollowStatus::REQUESTED
        ])->exists();
    }

    public function canFollow(): bool
    {
        $blockService = new BlockService($this->followerData, $this->followingData);

        if($blockService->blockedAny()) {
            return false;
        }

        return $this->followerData->id !== $this->followingData->id;
    }
}
