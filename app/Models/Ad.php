<?php

namespace App\Models;

use App\Support\Num;
use App\Enums\Ad\AdStatus;
use App\Enums\Ad\AdApproval;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;

class Ad extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => AdStatus::class,
        'approval' => AdApproval::class,
        'created_at' => ModelTimestampCast::class,
        'last_show_at' => ModelTimestampCast::class,
        'last_charge_at' => ModelTimestampCast::class
    ];

    public function scopeApproved($query)
    {
        return $query->where('approval', AdApproval::APPROVED);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeExcludeDraft($query)
    {
        return $query->where('status', '!=', AdStatus::DRAFT);
    }

    public function scopePublished($query)
    {
        return $query->where('status', AdStatus::PUBLISHED);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediaable', 'mediaable_type', 'mediaable_id', 'id', 'id');
    }

    public function getPreviewImageUrlAttribute()
	{
		$media = $this->media;

		if($media->isEmpty()) {
			return asset(config('ads.ad.default_preview'));
		}

		return $media->first()->source_url;
	}

    public function getFormattedIdAttribute(): string
    {
        return Num::leadingZero($this->id);
    }

    public function getFormattedSpentBudgetAttribute(): string
    {
        return Num::currency($this->spent_budget);
    }

    public function getFormattedTotalBudgetAttribute(): string
    {
        return Num::currency($this->total_budget);
    }

    public function getFormattedViewsCountAttribute(): string
    {
        return Num::abbreviate($this->views_count);
    }

    public function getFormattedPricePerViewAttribute(): string
    {
        return Num::currency($this->price_per_view);
    }
}
