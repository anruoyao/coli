<?php

namespace App\Http\Controllers\Api\Guest;

use App\Models\User;
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

        $cursorId = $request->integer('cursor');
        $contentType = (string) $request->input('filter.type', $request->input('type', 'posts'));

        $posts = GuestContentScope::posts()
            ->where('user_id', $user->id)
            ->when($contentType === 'media', function ($query) {
                // 媒体 tab：仅非文本帖（边界内的图/视频/音频等）
                $query->whereNot('type', \App\Enums\Post\PostType::TEXT);
            })
            ->when(! empty($cursorId), fn ($query) => $query->where('id', '<', $cursorId))
            ->latest('id')
            ->take((int) config('post.paginate_per'))
            ->get();

        return $this->responseSuccess([
            'data' => GuestPostCollection::make($posts),
        ]);
    }
}
