<?php

namespace App\Http\Controllers\Api\Guest;

use App\Actions\Ad\AdShowAction;
use App\Http\Resources\Guest\GuestPostCollection;
use App\Services\Ad\AdFeedInjectionService;
use App\Support\Guest\GuestContentScope;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * 访客精选公开帖子流（不依赖关注关系）。
 */
class GuestFeedController extends GuestController
{
    use SupportsApiResponses;

    public function feed(Request $request)
    {
        $filter = $request->array('filter');

        $page = data_get_integer($filter, 'page', 1);
        $onset = data_get_integer($filter, 'onset', 0);

        $posts = GuestContentScope::posts()
            ->excludeAds()
            ->when(! empty($onset), fn ($query) => $query->where('id', '>', $onset))
            ->orderBy('created_at', 'desc')
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('quotes_count', 'desc')
            ->simplePaginateManual((int) config('post.paginate_per'), $page);

        $postItems = Collection::make($posts->items());

        // 原生广告：访客流同样注入影子帖并计曝光（访客可看、不可互动）
        $injectedAds = app(AdFeedInjectionService::class)->inject(
            $postItems,
            isOnset: ! empty($onset)
        );

        foreach ($injectedAds as $adData) {
            defer(fn () => (new AdShowAction($adData))->execute());
        }

        return $this->responseSuccess([
            'data' => GuestPostCollection::make($postItems),
        ]);
    }
}
