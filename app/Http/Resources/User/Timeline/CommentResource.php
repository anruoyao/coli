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

namespace App\Http\Resources\User\Timeline;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            // 顶层主评论的 id（主评论自身为 null）；客户端据此把回复归并到线程
            'root_id' => $this->root_id,
            'has_parent' => (empty($this->parent_id)) ? false : true,
            'content' => e($this->content),
            // 线程回复总数（仅主评论在 threaded 模式下有值，其余为 0）
            'replies_total' => (int) ($this->getAttribute('thread_replies_count') ?? 0),
            'relations' => [
                'user' => [
                    'avatar_url' => $this->user->avatar_url,
                    'name' => $this->user->name,
                    'username' => $this->user->username,
                    'id' => $this->user->id
                ],
                'reactions' => ReactionCollection::make($this->reactions),
                'parent' => $this->getParentCommentData(),
                // 主评论折叠态下展示的最新 2 条回复（threaded 模式，未水合时省略）
                'preview_replies' => $this->relationLoaded('previewReplies')
                    ? CommentResource::collection($this->getRelation('previewReplies'))
                    : new MissingValue(),
            ],
            'date' => [
                'iso' => $this->created_at->getIso(),
                'time_ago' => $this->created_at->getTimeAgo()
            ],
            'meta' => [
                'permissions' => [
                    'can_edit' => auth_check() ? me()->can('update', $this->resource) : false,
                    'can_delete' => auth_check() ? me()->can('delete', $this->resource) : false
                ],
                'is_translatable' => $this->isContentTranslatable()
            ]
        ];
    }

    private function getParentCommentData()
    {
        if($this->parent) {
            return [
                'content' => Str::limit($this->parent->content, 120),
                'user' => [
                    'name' => $this->parent->user->name,
                    'username' => $this->parent->user->username,
                    'id' => $this->parent->user->id
                ]
            ];
        }
    }
}
