<?php

namespace App\Http\Controllers\Api\Guest;

use App\Models\User;
use App\Services\Profile\ProfilePostService;
use App\Http\Resources\Guest\GuestPostCollection;
use App\Http\Resources\Guest\GuestProfileResource;
use App\Support\Guest\GuestContentScope;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;

/**
 * 访客公开用户主页（脱敏）与其公开帖子（只读）。
 * 不提供粉丝/关注列表端点。
 */
class GuestProfileController extends GuestController
{
    use SupportsApiResponses;

    public function show(Request $request, string $username)
    {
        $user = User::active()
            ->withCount(['followers', 'followings', 'posts as publications_count'])
            ->where('username', $username)
            ->first();

        if (! $user) {
            return $this->responseResourceNotFoundError('User', $username);
        }

        return $this->responseSuccess([
            'data' => GuestProfileResource::make($user),
        ]);
    }

    public function posts(Request $request, string $username)
    {
        $user = User::activeByUsername($username)->first();

        if (! $user) {
            return $this->responseResourceNotFoundError('User', $username);
        }

        $request->validate([
            'cursor' => ['nullable', 'integer', 'min:0'],
            'type' => ['sometimes', 'in:posts,media'],
            'filter' => ['sometimes', 'array'],
            'filter.type' => ['sometimes', 'in:posts,media'],
        ]);

        $contentType = (string) $request->input('filter.type', $request->input('type', 'posts'));
        $page = app(ProfilePostService::class)->page(
            GuestContentScope::posts()->where('user_id', $user->id),
            $contentType,
            $request->integer('cursor')
        );

        return $this->responseSuccess([
            'data' => GuestPostCollection::make($page['posts']),
            'pinned' => GuestPostCollection::make($page['pinned']),
            'meta' => $page['meta'],
        ]);
    }
}
