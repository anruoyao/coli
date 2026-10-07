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

namespace App\Http\Controllers\Api\User\Explore;

use App\Models\Post;
use App\Models\User;
use App\Enums\Post\PostStatus;
use Illuminate\Http\Request;
use App\Actions\Ad\AdShowAction;
use App\Database\Configs\Table;
use App\Http\Controllers\Controller;
use App\Services\Ad\AdFeedInjectionService;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\People\PeopleCollection;
use App\Http\Resources\User\Timeline\TimelineCollection;
use App\Traits\Http\Controllers\Api\User\Explore\ValidatesPeopleFilters;

class ExploreController extends Controller
{
    use SupportsApiResponses,
        ValidatesPeopleFilters;

    private $filter = [];
    private $me = null;

    public function __construct()
    {
        $this->me = me();
    }

    public function getPeople(Request $request)
    {
        $filterOptions = $this->getValidatedFilters($request);

        $people = User::active()->author()->excludeSelf()->whereNotIn('id', function ($query) {
            $query->select('following_id')->from(Table::FOLLOWS)->where('follower_id', me()->id);
        })->whereNotIn('id', function($query) {
            $query->select('blocked_id')->from(Table::BLOCKS)->where('blocker_id', me()->id);
        })->whereNotIn('id', function($query) {
            $query->select('blocker_id')->from(Table::BLOCKS)->where('blocked_id', me()->id);
        })->unless(empty($filterOptions['query']), function ($query) use ($filterOptions) {
            $query->where(function($query) use ($filterOptions) {
                $query->whereLike('username', "%{$filterOptions['query']}%")
                    ->orWhereLike('first_name', "%{$filterOptions['query']}%")
                    ->orWhereLike('last_name', "%{$filterOptions['query']}%")
                    ->orWhereLike('city', "%{$filterOptions['query']}%")
                    ->orWhereLike('caption', "%{$filterOptions['query']}%")
                    ->orWhereLike('bio', "%{$filterOptions['query']}%");
            });
        })
        ->orderByDesc('followers_count')
        ->orderByDesc('publications_count')
        ->simplePaginateManual(30, (! empty($filterOptions['page']) ? $filterOptions['page'] : 1));

        return $this->responseSuccess([
            'data' => PeopleCollection::make($people->items())
        ]);
    }

    public function getPosts(Request $request)
    {
        $filter = $request->array('filter');

        $this->filter['page'] = data_get_integer($filter, 'page', 1);
        $this->filter['onset'] = data_get_integer($filter, 'onset', 0);

        $feedORMQuery = Post::timelineFormatPosts()
            ->excludeAds()
            ->when(! empty($this->filter['onset']), function($query) {
                $query->where('id', '>', $this->filter['onset']);
            })->when((! $this->me->isRoot()), function($query) {
                $query->where(function($query) {
                    $query->where('user_id', $this->me->id)->orWhereHas('user', function($u) {
                        $u->author();
                    });
                });
            })
            ->whereNotIn('user_id', function($query) {
                $query->select('blocked_id')->from(Table::BLOCKS)->where('blocker_id', me()->id);
            })->whereNotIn('user_id', function($query) {
                $query->select('blocker_id')->from(Table::BLOCKS)->where('blocked_id', me()->id);
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('quotes_count', 'desc');

        // 与关注时间线一致：把自己的视频处理中帖子并入推荐流（前端显示“处理中”提示）
        $processingPosts = $this->me->posts()->where('status', PostStatus::PROCESSING_VIDEO)->get();

        $timelinePosts = $feedORMQuery->simplePaginateManual(config('post.paginate_per'), $this->filter['page']);

        $timelinePosts = $processingPosts->merge($timelinePosts);

        // 原生广告：推荐流同样槽位注入影子帖 + 曝光计费
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
}
