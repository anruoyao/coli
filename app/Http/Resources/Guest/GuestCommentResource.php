<?php

namespace App\Http\Resources\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * 访客版评论：正文 + 作者预览 + 反应摘要 + 父评论摘要；
 * 权限全部 false（不可回复/删除/表态）。
 */
class GuestCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'has_parent' => ! empty($this->parent_id),
            'content' => e($this->content),
            'relations' => [
                'user' => GuestUserPreviewResource::make($this->user),
                'reactions' => GuestReactionSummary::map($this->reactions),
                'parent' => $this->getParentData(),
            ],
            'date' => [
                'iso' => $this->created_at?->getIso(),
                'time_ago' => $this->created_at?->getTimeAgo(),
            ],
            'meta' => [
                'permissions' => [
                    'can_edit' => false,
                    'can_delete' => false,
                    'can_reply' => false,
                    'can_react' => false,
                ],
            ],
        ];
    }

    private function getParentData(): ?array
    {
        if (! $this->parent) {
            return null;
        }

        return [
            'content' => Str::limit($this->parent->content, 120),
            'user' => [
                'id' => $this->parent->user->id,
                'name' => $this->parent->user->name,
                'username' => $this->parent->user->username,
            ],
        ];
    }
}
