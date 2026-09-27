<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENDING = 'sending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TARGET_ALL = 'all';
    public const TARGET_MANUAL = 'manual';
    public const TARGET_TYPE = 'type';

    public $table = Table::MARKETING_CAMPAIGNS;

    protected $casts = [
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'target_user_ids' => 'array',
        'email_recipient_count' => 'integer',
        'email_sent_count' => 'integer',
        'in_app_recipient_count' => 'integer',
        'in_app_sent_count' => 'integer',
        'created_by' => 'integer',
    ];

    protected $guarded = [];

    public function recipients(): HasMany
    {
        return $this->hasMany(MarketingCampaignRecipient::class, 'campaign_id', 'id');
    }
}