<?php

namespace App\Models;

use App\Database\Configs\Table;
use App\Support\Casts\ModelTimestampCast;
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

    // 标题字号档位
    public const TITLE_SIZES = ['sm', 'md', 'lg'];

    // 标题字重档位
    public const TITLE_WEIGHTS = ['normal', 'medium', 'semibold', 'bold'];

    // 通知/邮件内最多展示的关联帖子数
    public const MAX_POSTS = 3;

    public $table = Table::MARKETING_CAMPAIGNS;

    protected $casts = [
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'target_user_ids' => 'array',
        'post_ids' => 'array',
        'email_recipient_count' => 'integer',
        'email_sent_count' => 'integer',
        'in_app_recipient_count' => 'integer',
        'in_app_sent_count' => 'integer',
        'created_by' => 'integer',
        // 与项目其它模型一致：时间字段经 ModelTimestampCast 转为 DateFormatter（getFormatted() 等）
        'created_at' => ModelTimestampCast::class,
        'updated_at' => ModelTimestampCast::class,
    ];

    protected $guarded = [];

    public function recipients(): HasMany
    {
        return $this->hasMany(MarketingCampaignRecipient::class, 'campaign_id', 'id');
    }
}