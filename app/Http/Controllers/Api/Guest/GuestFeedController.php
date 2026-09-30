<?php

namespace App\Http\Controllers\Api\Guest;

use App\Http\Resources\Guest\GuestPostCollection;
use App\Support\Guest\GuestContentScope;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;

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
            ->when(! empty($onset), fn ($query) => $query->where('id', '>', $onset))
            ->orderBy('created_at', 'desc')
            ->orderBy('comments_count', 'desc')
            ->orderBy('bookmarks_count', 'desc')
            ->orderBy('views_count', 'desc')
            ->orderBy('quotes_count', 'desc')
            ->simplePaginateManual((int) config('post.paginate_per'), $page);

        return $this->responseSuccess([
            'data' => GuestPostCollection::make($posts),
        ]);
    }
}
