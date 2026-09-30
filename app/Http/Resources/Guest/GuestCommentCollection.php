<?php

namespace App\Http\Resources\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * 访客评论集合：纯数组输出（与登录态 CommentCollection 消费方式一致）。
 */
class GuestCommentCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($comment) {
            return GuestCommentResource::make($comment->resource);
        })->values()->all();
    }
}
