<?php

namespace App\Http\Resources\Guest;

use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 访客版帖子：结构与登录态 TimelineResource 对齐（前端组件可复用），
 * 但去除所有身份相关字段——permissions 全 false、不输出 activity；
 * 内容可见性边界（ACTIVE/非敏感）由查询层保证。
 */
class GuestPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'content' => e($this->content),
            'type' => $this->type,
            'text_language' => $this->text_language,
            'hash_id' => $this->hash_id,
            'relations' => [
                'user' => GuestUserPreviewResource::make($this->user),
                'reactions' => GuestReactionSummary::map($this->reactions),
                'comments' => $this->previewComments(),
            ],
            'views_count' => [
                'raw' => $this->views_count,
                'formatted' => Num::abbreviate($this->views_count),
            ],
            'comments_count' => [
                'raw' => $this->comments_count,
                'formatted' => Num::abbreviate($this->comments_count),
            ],
            'date' => [
                'iso' => $this->created_at?->getIso(),
                'time_ago' => $this->created_at?->getTimeAgo(),
                'timestamp' => $this->created_at?->getTimestamp(),
            ],
            'meta' => [
                'permissions' => [
                    'can_like' => false,
                    'can_comment' => false,
                    'can_edit' => false,
                    'can_delete' => false,
                    'can_report' => false,
                ],
                'is_translatable' => $this->isContentTranslatable(),
                'is_quoting' => $this->is_quoting,
                'is_sensitive' => false,
                'is_ai_generated' => $this->is_ai_generated,
            ],
        ];

        if ($this->type->isMedia()) {
            $data['relations']['media'] = $this->media->map(function ($item) {
                return GuestMediaResource::make($item);
            })->values();
        } elseif ($this->type->isPoll()) {
            $data['relations']['poll'] = GuestPollResource::make($this->poll);
        }

        if ($this->quotedPost) {
            $data['relations']['quoted_post'] = GuestQuoteResource::make($this->quotedPost);
        }

        if ($this->linkSnapshot) {
            $data['relations']['link_snapshot'] = [
                'id' => $this->linkSnapshot->id,
                'title' => $this->linkSnapshot->title,
                'description' => $this->linkSnapshot->description,
                'url' => $this->linkSnapshot->url,
                'metadata' => $this->linkSnapshot->metadata,
            ];
        }

        return $data;
    }

    private function previewComments(): array
    {
        return $this->comments->unique('user.id')->map(function ($item) {
            return [
                'id' => $item->id,
                'user' => [
                    'avatar_url' => $item->user->avatar_url,
                ],
            ];
        })->values()->toArray();
    }
}
