<?php

namespace App\Services\Ad;

use App\Models\Ad;
use App\Models\Post;
use App\Enums\User\UserStatus;
use Illuminate\Support\Collection;

/**
 * 原生广告信息流注入。
 *
 * 在一页 feed 帖子集合的固定槽位（第 N 条帖子之后）插入广告影子帖，
 * 广告选择按 last_show_at 轮换（refresh_interval 冷却），避免翻页重复。
 */
class AdFeedInjectionService
{
    /**
     * 向一页帖子集合注入广告影子帖（原地 splice）。
     *
     * @param Collection $posts 一页帖子集合，会被原地修改
     * @param bool $isOnset 拉新请求（id > onset）不注入
     * @return Collection 注入的广告（Ad 模型），供调用方 defer 曝光计费
     */
    public function inject(Collection $posts, bool $isOnset = false): Collection
    {
        if (! config('ads.feed.enabled') || $isOnset || $posts->isEmpty()) {
            return collect();
        }

        $adPosts = $this->selectAdPosts();

        if ($adPosts->isEmpty()) {
            return collect();
        }

        $slots = (array) config('ads.feed.slots');
        $inserted = 0;

        foreach ($slots as $index => $slot) {
            if (! isset($adPosts[$index])) {
                break;
            }

            // 槽位从 1 计：第 slot 条帖子之后插入；已插入的广告使后续索引顺延
            $position = (int) $slot + $inserted;

            if ($position > $posts->count()) {
                break;
            }

            $posts->splice($position, 0, [$adPosts[$index]]);
            $inserted++;
        }

        return $adPosts->take($inserted)->map(fn (Post $post) => $post->ad);
    }

    /**
     * 选取可投放的广告影子帖（帖子形态、预加载与 timelineFormatPosts 对齐）。
     */
    private function selectAdPosts(): Collection
    {
        $limit = count((array) config('ads.feed.slots'));

        $adIds = $this->eligibleAdIds($limit, freshOnly: true);

        // 轮换兜底：所有广告都在冷却期内时，取最久未展示的（仅剩一条广告时可能跨页重复）
        if ($adIds->isEmpty()) {
            $adIds = $this->eligibleAdIds($limit, freshOnly: false);
        }

        if ($adIds->isEmpty()) {
            return collect();
        }

        return Post::query()
            ->whereNotNull('ad_id')
            ->whereIn('ad_id', $adIds)
            ->active()
            ->with([
                'user',
                'reactions',
                'ad.media',
                'comments' => function ($query) {
                    $query->with('user:id,avatar')->limit(3);
                },
            ])
            ->get();
    }

    /**
     * 可投放广告 id 列表：已发布 + 已审批 + 预算未尽 + 作者 ACTIVE + 影子帖在线。
     */
    private function eligibleAdIds(int $limit, bool $freshOnly): Collection
    {
        return Ad::query()
            ->published()
            ->approved()
            ->whereColumn('spent_budget', '<', 'total_budget')
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::ACTIVE))
            ->whereHas('post', fn ($query) => $query->active())
            ->when($freshOnly, function ($query) {
                // 冷却轮换：最近 refresh_interval 内展示过的广告不再选中
                $query->where(function ($query) {
                    $query->whereNull('last_show_at')
                        ->orWhere('last_show_at', '<', now()->subMinutes(config('ads.refresh_interval')));
                })->inRandomOrder();
            }, function ($query) {
                $query->orderByRaw('last_show_at IS NULL DESC, last_show_at ASC');
            })
            ->take($limit)
            ->pluck('id');
    }
}
