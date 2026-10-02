<?php

namespace App\Http\Controllers\Api\Guest;

use App\Enums\User\UserStatus;
use App\Http\Resources\Guest\GuestCommentCollection;
use App\Http\Resources\Guest\GuestPostResource;
use App\Http\Resources\Guest\GuestUserPreviewResource;
use App\Services\Comment\CommentThreadService;
use App\Support\Guest\GuestContentScope;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;

/**
 * 访客公开帖子详情与评论（只读）。
 */
class GuestPostController extends GuestController
{
    use SupportsApiResponses;

    public function show(Request $request, string $hashId)
    {
        $post = GuestContentScope::findByHashId($hashId);

        if (! $post) {
            return $this->responseResourceNotFoundError('Post', $hashId);
        }

        $comments = $request->boolean('threaded')
            ? $this->fetchThreadedRoots($post)
            : $this->fetchComments($post);

        return $this->responseSuccess([
            'data' => [
                'author' => GuestUserPreviewResource::make($post->user),
                'post' => GuestPostResource::make($post),
                'comments' => GuestCommentCollection::make($comments),
                'meta' => [
                    'comments_per_page' => (int) config('post.comments.paginate_per'),
                ],
            ],
        ]);
    }

    public function comments(Request $request, string $hashId)
    {
        $post = GuestContentScope::findByHashId($hashId);

        if (! $post) {
            return $this->responseResourceNotFoundError('Post', $hashId);
        }

        $cursorId = $request->integer('cursor');

        if ($request->boolean('threaded')) {
            return $this->responseSuccess([
                'data' => GuestCommentCollection::make($this->fetchThreadedRoots($post, $cursorId)),
            ]);
        }

        return $this->responseSuccess([
            'data' => GuestCommentCollection::make($this->fetchComments($post, $cursorId)),
        ]);
    }

    /**
     * 主评论线程下的回复分页（只读，仅 ACTIVE 作者）。
     */
    public function commentReplies(Request $request, CommentThreadService $threads, string $id)
    {
        $rootId = (int) $id;

        $rootComment = $threads->findRootComment($rootId);

        if (! $rootComment) {
            return $this->responseResourceNotFoundError('Comment', $rootId);
        }

        $post = GuestContentScope::findById($rootComment->post_id);

        if (! $post) {
            return $this->responseResourceNotFoundError('Post', $rootComment->post_id);
        }

        $result = $threads->repliesForRoot(
            $rootId,
            $request->integer('cursor'),
            (int) config('post.comments.paginate_per'),
            true
        );

        return $this->responseSuccess([
            'data' => GuestCommentCollection::make($result['items']),
            'meta' => [
                'total' => $result['total'],
            ],
        ]);
    }

    /**
     * 取公开评论：作者须 ACTIVE；固定预加载与条数；游标按 id 向下翻页。
     */
    private function fetchComments($post, int|string $cursorId = 0)
    {
        return $post->comments()
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::ACTIVE))
            ->with([
                'post:id,user_id',
                'user:id,first_name,last_name,avatar,username',
                'reactions',
                'parent' => fn ($q) => $q->select('id', 'content', 'user_id'),
                'parent.user:id,first_name,last_name,username',
            ])
            ->when(! empty($cursorId), fn ($query) => $query->where('id', '<', $cursorId))
            ->latest('id')
            ->take((int) config('post.comments.paginate_per'))
            ->get();
    }

    /**
     * 树状模式：仅主评论 + 回复预览（作者 ACTIVE）。
     */
    private function fetchThreadedRoots($post, int|string $cursorId = 0)
    {
        $threads = app(CommentThreadService::class);

        $roots = $threads->rootsForPost(
            $post,
            (int) $cursorId,
            (int) config('post.comments.paginate_per'),
            true
        );

        $threads->hydrateThreadPreviews($roots, true);

        return $roots;
    }
}
