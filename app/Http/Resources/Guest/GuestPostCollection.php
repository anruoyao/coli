<?php

namespace App\Http\Resources\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * 访客帖子集合：输出结构与登录态 TimelineCollection 一致（纯数组），
 * 前端列表 store 可以相同方式消费。
 */
class GuestPostCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($post) {
            return GuestPostResource::make($post->resource);
        })->values()->all();
    }
}
