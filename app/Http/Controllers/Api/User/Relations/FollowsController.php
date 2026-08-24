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

namespace App\Http\Controllers\Api\User\Relations;

use App\Constants\Notifications;
use App\Constants\Relationship;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\User\Follows\FollowAcceptNotification;
use App\Notifications\User\Follows\FollowRequestNotification;
use App\Notifications\User\Follows\NewFollowerNotification;
use App\Services\Relations\FollowService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FollowsController extends Controller
{
    use SupportsApiResponses;

    public function followUser(Request $request)
    {
        $userId = $request->integer('id', 0);

        $userData = User::activeById($userId)->first();

        if($userData) {
            $followService = new FollowService(me(), $userData);

            // Check if Me is Following or Follow Requested. Unfollow if true.
            if($followService->isFollowing() || $followService->followRequested()) {
                $followService->unfollow();
            }
            else {
                if($followService->canFollow()) {
                    $follow = $followService->follow();

                    if($follow->status->isRequested()) {
                        $userData->notify(new FollowRequestNotification());
                    }
                    else if($follow->status->isFollowing()) {
                        $userData->notify(new NewFollowerNotification());
                    }
                }
            }

            return $this->responseSuccess([
                'data' => [
                    'relationship' => [
                        Relationship::FOLLOW_GROUP => [
                            Relationship::FOLLOWING => $followService->isFollowing(),
                            Relationship::REQUESTED => $followService->followRequested()
                        ]
                    ]
                ]
            ]);
        }

        return $this->responseResourceNotFoundError('User', $userId);
    }

    public function acceptFollowRequest(Request $request)
    {
        $userId = $request->integer('id', 0);

        $userData = User::activeById($userId)->first();

        if($userData) {
            $followService = new FollowService($userData, me());
            $followService->accept();

            // 清理该请求者发给我的「关注请求」通知，避免刷新后残留幽灵请求
            $this->clearFollowRequestNotifications($userData->id);

            $userData->notify(new FollowAcceptNotification());

            return $this->responseSuccess([
                'data' => null
            ]);
        }

        return $this->responseResourceNotFoundError('User', $userId);
    }

    public function declineFollowRequest(Request $request)
    {
        $userId = $request->integer('id', 0);

        $userData = User::activeById($userId)->first();

        if($userData) {
            $followService = new FollowService($userData, me());
            $followService->decline();

            // 清理该请求者发给我的「关注请求」通知，避免刷新后残留幽灵请求
            $this->clearFollowRequestNotifications($userData->id);

            return $this->responseSuccess([
                'data' => null
            ]);
        }

        return $this->responseResourceNotFoundError('User', $userId);
    }

    /**
     * 删除某个请求者发给当前用户的所有「关注请求」通知。
     */
    private function clearFollowRequestNotifications(int $requesterUserId): void
    {
        DB::table('notifications')
            ->where('notifiable_id', me()->id)
            ->where('type', Notifications::FOLLOWED_REQUESTED)
            ->whereJsonContains('data->actor->id', $requesterUserId)
            ->delete();
    }
}
