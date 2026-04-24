<?php
namespace App\Models;

use App\MediaApi\Giphy\Giphy;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaVisibility;
use App\Events\Media\MediaCreatedEvent;
use App\Events\Media\MediaDeletedEvent;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $dispatchesEvents = [
        'created' => MediaCreatedEvent::class,
        'deleted' => MediaDeletedEvent::class,
    ];

    public $guarded = [];

    public static $snakeAttributes = false;

    protected $attributes = [
        'metadata' => '[]'
    ];

    public $casts = [
        'metadata' => 'array',
        'type' => MediaType::class,
        'status' => MediaStatus::class,
        'visibility' => MediaVisibility::class
    ];

    public function mediaable()
    {
        return $this->morphTo('mediaable', 'mediaable_id', 'mediaable_type', 'id', 'id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'mediaable_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'mediaable_id', 'id');
    }

    public function storyFrame()
    {
        return $this->belongsTo(StoryFrame::class, 'mediaable_id', 'id');
    }

    public function getSourceUrlAttribute()
    {
        if ($this->disk == Giphy::getDisk()) {
            return $this->source_path;
        }

        if($this->type->isDocument()) {
            return route('downloads.document.index', ['mediaId' => $this->id]);
        }
        else if($this->type->isAudio() || $this->type->isVideo()) {
            if(! $this->status->isProcessed()) {
                return null;
            }
        }

        return storage_url($this->source_path, $this->disk);
    }

    public function getThumbnailUrlAttribute()
    {
        if(empty($this->thumbnail_path)) {
            return null;
        }

        return storage_url($this->thumbnail_path, $this->thumbnail_disk);
    }
}
