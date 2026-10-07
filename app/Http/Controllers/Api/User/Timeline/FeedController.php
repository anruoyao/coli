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

namespace App\Http\Controllers\Api\User\Timeline;

use App\Models\Post;
use Illuminate\Http\Request;
use App\Enums\Post\PostStatus;
use App\Database\Configs\Table;
use App\Actions\Ad\AdShowAction;
use App\Http\Controllers\Controller;
use App\Services\Ad\AdFeedInjectionService;
use App\Services\Comment\CommentThreadService;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Timeline\TimelineResource;
use App\Http\Resources\User\Timeline\CommentCollection;
use App\Http\Resources\User\Timeline\TimelineCollection;
use App\Http\Resources\User\Overview\UserOverviewResource;

class FeedController extends Controller
{
    use SupportsApiResponses;

    private $me = null;
    private $filter = [];

    public function __construct()
    {
        $this->me = me();
    }

    public function getFeed()
    {
        $filter = request()->array('filter');

        $this->filter['page'] = data_get_integer($filter, 'page', 1);
        $this->filter['onset'] = data_get_integer($filter, 'onset', 0);

        $processingPosts = $this->me->posts()->where('status', PostStatus::PROCESSING_VIDEO)->get();

        $feedORMQuery = Post::timelineFormatPosts()
            ->excludeAds()
            ->when(! empty($this->filter['onset']), function($query) {
                $query->where('id', '>', $this->filter['onset']);
            })->when((! $this->me->isRoot()), function($query) {
                $query->where(function($query) {
                    $query->where('user_id', $this->me->id)->orWhereHas('user', function($u) {
                        $u->whereIn('user_id', function($query) {
                            $query->select('following_id')->from(Table::FOLLOWS)->where('follower_id', $this->me->id);
                        })->author();
                    });
                });
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('quotes_count', 'desc');

        $timelinePosts = $feedORMQuery->simplePaginateManual(config('post.paginate_per'), $this->filter['page']);

        $timelinePosts = $processingPosts->merge($timelinePosts);

        // 原生广告：槽位注入影子帖 + 曝光计费（defer，沿用 charge_interval 去重语义）
        $injectedAds = app(AdFeedInjectionService::class)->inject(
            $timelinePosts,
            isOnset: ! empty($this->filter['onset'])
        );

        foreach ($injectedAds as $adData) {
            defer(fn () => (new AdShowAction($adData))->execute());
        }

        return $this->responseSuccess([
            'data' => TimelineCollection::make($timelinePosts)
        ]);
    }

    public function getPostData(Request $request)
    {
        $postHashId = $request->route('hashId');

        $postData = Post::active()->whereHashId($postHashId)->timelineFormatPosts()->first();
        
        if($postData) {
            $postComments = $request->boolean('threaded')
                ? $this->fetchThreadedRoots($postData)
                : $this->fetchPostItemComments($postData);

            return $this->responseSuccess([
                'data' => [
                    'author' => UserOverviewResource::make($postData->user),
                    'post' => TimelineResource::make($postData),
                    'comments' => CommentCollection::make($postComments),
                    'meta' => [
                        'comments_per_page' => config('post.comments.paginate_per')
                    ]
                ]
            ]);
        }

        else{
            return $this->responseResourceNotFoundError('Post', $postHashId);
        }
    }

    public function getPostComments(Request $request)
    {
        $postHashId = $request->route('hashId');
        $cursorId = $request->integer('cursor');

        $postData = Post::active()->whereHashId($postHashId)->first();

        if(empty($postData)) {
            return $this->responseResourceNotFoundError('Post', $postHashId);
        }

        if ($request->boolean('threaded')) {
            $roots = $this->fetchThreadedRoots($postData, $cursorId);

            return $this->responseSuccess([
                'data' => CommentCollection::make($roots)
            ]);
        }

        $postComments = $this->fetchPostItemComments($postData, $cursorId);

        return $this->responseSuccess([
            'data' => CommentCollection::make($postComments)
        ]);
    }

    /**
     * 某条主评论线程下的全部回复（游标分页，id DESC）。
     */
    public function getCommentReplies(Request $request, CommentThreadService $threads)
    {
        $rootId = (int) $request->route('id');

        $rootComment = $threads->findRootComment($rootId);

        if (empty($rootComment)) {
            return $this->responseResourceNotFoundError('Comment', $rootId);
        }

        $postData = Post::active()->where('id', $rootComment->post_id)->first();

        if (empty($postData)) {
            return $this->responseResourceNotFoundError('Post', $rootComment->post_id);
        }

        $result = $threads->repliesForRoot(
            $rootId,
            $request->integer('cursor'),
            (int) config('post.comments.paginate_per')
        );

        return $this->responseSuccess([
            'data' => CommentCollection::make($result['items']),
            'meta' => [
                'total' => $result['total']
            ]
        ]);
    }

    private function fetchPostItemComments(Post $postData, int|string $cursorId = 0)
    {
        $postComments = $postData->comments()->with([
            'post:id,user_id',
            'user:id,first_name,last_name,avatar,username',
            'reactions',
            'parent.user:id,first_name,last_name,username'
        ])->when($cursorId, function($query) use ($cursorId) {
            $query->where('id', '<', $cursorId);
        })->latest('id');

        return $postComments->take(config('post.comments.paginate_per'))->get();
    }

    /**
     * 树状模式：只返回主评论，并水合 replies_total + 最新回复预览。
     */
    private function fetchThreadedRoots(Post $postData, int|string $cursorId = 0)
    {
        $threads = app(CommentThreadService::class);

        $roots = $threads->rootsForPost(
            $postData,
            (int) $cursorId,
            (int) config('post.comments.paginate_per')
        );

        $threads->hydrateThreadPreviews($roots);

        return $roots;
    }
}
