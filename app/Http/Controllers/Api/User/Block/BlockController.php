<?php

namespace App\Http\Controllers\Api\User\Block;

use App\Models\User;
use Illuminate\Http\Request;
use App\Constants\Relationship;
use App\Http\Controllers\Controller;
use App\Actions\User\BlockUserAction;
use App\Actions\User\UnblockUserAction;
use App\Http\Resources\User\People\PeopleCollection;
use App\Traits\Http\Api\SupportsApiResponses;

class BlockController extends Controller
{
    use SupportsApiResponses;

    public function blockUser(Request $request)
    {
        $userId = $request->integer('id', 0);

        if ($userId === me()->id) {
            return $this->responseValidationError([
                'message' => __('api/error.cannot_block_self'),
                'errors' => ['id' => [__('api/error.cannot_block_self')]]
            ]);
        }

        $userData = User::activeById($userId)->first();

        if ($userData) {
            if (me()->hasBlocked($userData)) {
                return $this->responseSuccess([
                    'data' => [
                        'relationship' => [
                            Relationship::BLOCK_GROUP => [
                                Relationship::BLOCKING => true,
                                Relationship::BLOCKED_BY => me()->isBlockedBy($userData)
                            ]
                        ]
                    ]
                ]);
            }

            (new BlockUserAction(user: me(), targetUser: $userData))->execute();

            return $this->responseSuccess([
                'data' => [
                    'relationship' => [
                        Relationship::BLOCK_GROUP => [
                            Relationship::BLOCKING => true,
                            Relationship::BLOCKED_BY => me()->isBlockedBy($userData)
                        ]
                    ]
                ]
            ]);
        }

        return $this->responseResourceNotFoundError('User', $userId);
    }

    public function unblockUser(Request $request)
    {
        $userId = $request->integer('id', 0);

        $userData = User::activeById($userId)->first();

        if ($userData) {
            if (! me()->hasBlocked($userData)) {
                return $this->responseSuccess([
                    'data' => [
                        'relationship' => [
                            Relationship::BLOCK_GROUP => [
                                Relationship::BLOCKING => false,
                                Relationship::BLOCKED_BY => me()->isBlockedBy($userData)
                            ]
                        ]
                    ]
                ]);
            }

            (new UnblockUserAction(user: me(), targetUser: $userData))->execute();

            return $this->responseSuccess([
                'data' => [
                    'relationship' => [
                        Relationship::BLOCK_GROUP => [
                            Relationship::BLOCKING => false,
                            Relationship::BLOCKED_BY => me()->isBlockedBy($userData)
                        ]
                    ]
                ]
            ]);
        }

        return $this->responseResourceNotFoundError('User', $userId);
    }

    public function getBlockedUsers(Request $request)
    {
        $cursorId = $request->integer('cursor', 0);

        $blocked = me()->blockedUsers()
            ->with('blockedUser')
            ->when($cursorId, function ($query) use ($cursorId) {
                $query->where('id', '<', $cursorId);
            })
            ->latest('id')
            ->take(config('user.blocked_users_paginate_per', 30))
            ->get();

        $people = $blocked->map(function ($block) {
            $block->blockedUser->cursor_id = $block->id;
            return $block->blockedUser;
        });

        return $this->responseSuccess([
            'data' => PeopleCollection::make($people)
        ]);
    }
}
