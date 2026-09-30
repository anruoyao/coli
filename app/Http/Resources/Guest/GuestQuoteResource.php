<?php

namespace App\Http\Resources\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 访客版引用帖（受限深度）：正文 + 作者预览 + 媒体；
 * 不再继续向下嵌套，防止无限展开。
 */
class GuestQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'content' => e($this->content),
            'type' => $this->type,
            'hash_id' => $this->hash_id,
            'relations' => [
                'user' => GuestUserPreviewResource::make($this->user),
                'media' => [],
            ],
            'date' => [
                'iso' => $this->created_at?->getIso(),
                'time_ago' => $this->created_at?->getTimeAgo(),
            ],
        ];

        if ($this->type->isMedia()) {
            $data['relations']['media'] = $this->media->map(function ($item) {
                return GuestMediaResource::make($item);
            })->values();
        }

        return $data;
    }
}
