<?php

namespace App\Support\Guest;

use App\Enums\Post\PostStatus;
use App\Enums\User\UserStatus;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;

/**
 * 访客内容可见性约束（唯一查询入口）。
 *
 * 所有访客帖子查询必须经本类：
 * - 帖子 status=ACTIVE、is_sensitive=false；
 * - 作者 status=ACTIVE（whereHas，防止封禁作者内容继续外显）；
 * - 固定预加载（含 media/poll，避免资源层 N+1 与懒加载）；
 * - 引用帖施加同一可见性边界；
 * - 排序/分页/条数由调用方按固定白名单施加，客户端不可自定义 where/order。
 */
class GuestContentScope
{
    public static function posts(): Builder
    {
        return Post::query()
            ->where('status', PostStatus::ACTIVE)
            ->where('is_sensitive', false)
            ->whereHas('user', fn (Builder $query) => $query->where('status', UserStatus::ACTIVE))
            ->with([
                'user',
                'reactions',
                'media',
                'poll',
                'linkSnapshot',
                'quotedPost' => function ($query) {
                    $query->where('status', PostStatus::ACTIVE)
                        ->where('is_sensitive', false)
                        ->whereHas('user', fn (Builder $u) => $u->where('status', UserStatus::ACTIVE))
                        ->with(['user', 'media']);
                },
                'comments' => function ($query) {
                    $query->with('user:id,avatar')->limit(3);
                },
            ]);
    }

    public static function findByHashId(string $hashId): ?Post
    {
        return self::posts()->whereHashId($hashId)->first();
    }

    public static function findById(int $postId): ?Post
    {
        return self::posts()->where('id', $postId)->first();
    }
}
