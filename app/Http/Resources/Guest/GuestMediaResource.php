<?php

namespace App\Http\Resources\Guest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * 访客版媒体资源：只读展示字段；不暴露存储路径以外的内部信息。
 */
class GuestMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_url' => $this->source_url,
            'extension' => $this->extension,
            'type' => $this->type->value,
            'size' => $this->size,
            'thumbnail_url' => $this->thumbnail_url,
            'lqip_base64' => $this->lqip_base64,
            'metadata' => $this->getMetadata(),
        ];
    }

    private function getMetadata(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        if ($this->type->isVideo()) {
            return Arr::only($metadata, ['duration', 'is_portrait']);
        }

        return $metadata;
    }
}
