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

namespace App\Http\Controllers\Api\User\Follows;

use App\Models\User;
use Illuminate\Http\Request;
use App\Constants\Relationship;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Notifications\User\Follows\NewFollowerNotification;
use App\Notifications\User\Follows\FollowAcceptNotification;
use App\Notifications\User\Follows\FollowRequestNotification;

class FollowsController extends Controller
{
    use SupportsApiResponses;

    public function followUser(Request $request)
    {
        $userId = $request->integer('id', 0);

        $userData = User::activeById($userId)->first();
        
        if($userData) {
            if(me()->isFollowing($userData) || me()->followRequested($userData)) {
                me()->unFollow($userData);
            }
            else {
                if(me()->canFollow($userData)) {
                    $follow = me()->follow($userData);

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
                            Relationship::FOLLOWING => me()->isFollowing($userData),
                            Relationship::REQUESTED => me()->followRequested($userData)
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
            $follow = me()->acceptFollowRequest($userData);

            if($follow && $follow->status->isFollowing()) {
                $userData->notify(new FollowAcceptNotification());
            }

            return $this->responseSuccess([
                'data' => null
            ]);
        }

        return $this->responseResourceNotFoundError('User', $userId);
    }
}
