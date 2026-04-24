<?php

namespace App\Http\Resources\User\Media;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mediaItem = [
            'id' => $this->id,
            'mediaable_id' => $this->mediaable_id,
            'source_url' => $this->source_url,
            'extension' => $this->extension,
            'type' => $this->type->value,
            'size' => $this->size,
            'status' => $this->status->value,
            'thumbnail_url' => $this->thumbnail_url,
            'thumbnail_size' => $this->thumbnail_size,
            'lqip_base64' => $this->lqip_base64,
            'metadata' => $this->getMetadata($this->metadata)
        ];

        return $mediaItem;
    }

    private function getMetadata(array $metadata)
    {
        if(is_array($metadata)) {
            if($this->type->isVideo()) {
                $metadata = Arr::only($metadata, [
                    'duration',
                    'is_portrait'
                ]);
            }

            return $metadata;
        }
        
        return [];
    }
}