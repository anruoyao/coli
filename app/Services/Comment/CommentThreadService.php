<?php

namespace App\Services\Comment;

use App\Enums\User\UserStatus;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * B站式树状评论线程查询：
 * - 主评论（parent_id NULL）单独分页；
 * - 任意层级的回复按 root_id 归并到主评论线程；
 * - 每个主评论携带 replies_total + 最新 N 条 preview_replies；
 * - 回复展开后独立游标分页（id DESC）。
 */
class CommentThreadService
{
    public const PREVIEW_PER_THREAD = 2;

    /**
     * 评论统一预加载（与时间线旧接口保持一致，保证 Resource 字段齐全）。
     */
    private function eagerRelations(): array
    {
        return [
            'post:id,user_id',
            'user:id,first_name,last_name,avatar,username',
            'reactions',
            'parent.user:id,first_name,last_name,username',
        ];
    }

    /**
     * 访客场景：只展示 ACTIVE 作者的评论。
     */
    private function scopeActiveUsers($query, bool $activeUsersOnly): void
    {
        if ($activeUsersOnly) {
            $query->whereHas('user', fn ($q) => $q->where('status', UserStatus::ACTIVE));
        }
    }

    /**
     * 主评论分页（id DESC 游标）。
     */
    public function rootsForPost(Post $post, int $cursorId, int $perPage, bool $activeUsersOnly = false): Collection
    {
        $query = $post->comments()
            ->with($this->eagerRelations())
            ->whereNull('parent_id')
            ->when(! empty($cursorId), fn ($q) => $q->where('id', '<', $cursorId))
            ->latest('id');

        $this->scopeActiveUsers($query, $activeUsersOnly);

        return $query->take($perPage)->get();
    }

    /**
     * 为一批主评论挂载：
     * - thread_replies_count（线程回复总数，含任意层级）；
     * - previewReplies 关联（每线程最新 PREVIEW_PER_THREAD 条）。
     */
    public function hydrateThreadPreviews(Collection $roots, bool $activeUsersOnly = false): void
    {
        if ($roots->isEmpty()) {
            return;
        }

        $rootIds = $roots->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idList = implode(',', $rootIds);

        // 每线程回复总数
        $totalsQuery = Comment::query()
            ->whereIn('root_id', $rootIds)
            ->select('root_id', DB::raw('COUNT(*) AS aggregate'))
            ->groupBy('root_id');
        $this->scopeActiveUsers($totalsQuery, $activeUsersOnly);
        $totals = $totalsQuery->pluck('aggregate', 'root_id');

        // 每线程最新 N 条：ROW_NUMBER 窗口函数（MySQL 8），避免 with()->limit() 只对全局生效
        $rankedSql = '(SELECT id, root_id, ROW_NUMBER() OVER (PARTITION BY root_id ORDER BY id DESC) AS rn'
            . ' FROM ' . Comment::query()->getModel()->getTable()
            . ' WHERE root_id IN (' . $idList . ')) AS ranked';

        $previewIds = DB::table(DB::raw($rankedSql))
            ->where('rn', '<=', self::PREVIEW_PER_THREAD)
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        $previews = collect();
        if (! empty($previewIds)) {
            $previewQuery = Comment::query()
                ->with($this->eagerRelations())
                ->whereIn('id', $previewIds)
                ->latest('id');
            $this->scopeActiveUsers($previewQuery, $activeUsersOnly);
            $previews = $previewQuery->get();
        }

        $grouped = $previews->groupBy(fn ($reply) => (int) $reply->root_id);

        foreach ($roots as $root) {
            $rootId = (int) $root->id;
            $root->setAttribute('thread_replies_count', (int) ($totals[$rootId] ?? 0));
            $root->setRelation('previewReplies', $grouped->get($rootId, collect())->values());
        }
    }

    /**
     * 线程内回复分页（id DESC 游标）。
     *
     * @return array{items: Collection, total: int}
     */
    public function repliesForRoot(int $rootId, int $cursorId, int $perPage, bool $activeUsersOnly = false): array
    {
        $totalQuery = Comment::query()->where('root_id', $rootId);
        $this->scopeActiveUsers($totalQuery, $activeUsersOnly);
        $total = (clone $totalQuery)->count();

        $itemsQuery = Comment::query()
            ->with($this->eagerRelations())
            ->where('root_id', $rootId)
            ->when(! empty($cursorId), fn ($q) => $q->where('id', '<', $cursorId))
            ->latest('id')
            ->take($perPage);
        $this->scopeActiveUsers($itemsQuery, $activeUsersOnly);

        return [
            'items' => $itemsQuery->get(),
            'total' => (int) $total,
        ];
    }

    /**
     * 按 id 查找主评论（不含回复）。
     */
    public function findRootComment(int $rootId): ?Comment
    {
        return Comment::query()
            ->whereNull('parent_id')
            ->where('id', $rootId)
            ->first();
    }
}
