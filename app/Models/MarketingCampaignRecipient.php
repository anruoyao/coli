<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignRecipient extends Model
{
    // 邮件通道状态
    public const EMAIL_PENDING = 'pending';
    public const EMAIL_QUEUED = 'queued';
    public const EMAIL_SENT = 'sent';
    public const EMAIL_FAILED = 'failed';
    public const EMAIL_SKIPPED = 'skipped';

    // 站内通知通道状态
    public const INAP_PENDING = 'pending';
    public const INAP_QUEUED = 'queued';
    public const INAP_SENT = 'sent';
    public const INAP_FAILED = 'failed';
    public const INAP_SKIPPED = 'skipped';

    public $table = Table::MARKETING_CAMPAIGN_RECIPIENTS;

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected $guarded = [];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}