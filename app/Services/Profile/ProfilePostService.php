<?php

namespace App\Services\Profile;

use App\Actions\Post\SetProfilePinAction;
use App\Enums\Post\PostType;
use Illuminate\Database\Eloquent\Builder;

class ProfilePostService
{
    public function page(Builder $query, string $type, int $cursor): array
    {
        $query = (clone $query)->excludeAds();
        $limit = max(1, (int) config('post.paginate_per'));
        $pinned = collect();

        if ($type === 'posts') {
            if ($cursor === 0) {
                $pinned = (clone $query)->where('profile_pinned', true)
                    ->orderByDesc('profile_pinned_at')->orderByDesc('id')
                    ->limit(SetProfilePinAction::LIMIT)->get();
            }

            $query->where('profile_pinned', false);
        } else {
            $query->whereNot('type', PostType::TEXT);
        }

        $posts = $query->when($cursor > 0, fn ($query) => $query->where('id', '<', $cursor))
            ->orderByDesc('id')->limit($limit + 1)->get();
        $hasMore = $posts->count() > $limit;
        $posts = $posts->take($limit)->values();

        return [
            'posts' => $posts,
            'pinned' => $pinned,
            'meta' => [
                'next_cursor' => $hasMore ? $posts->last()->id : null,
                'has_more' => $hasMore,
            ],
        ];
    }
}
