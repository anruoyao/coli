<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportEmailLog extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public $table = Table::REPORT_EMAIL_LOGS;

    protected $guarded = [];

    protected $casts = [
        'recipient_email' => 'encrypted',
        'sent_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id', 'id');
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'attempts' => $this->attempts + 1,
            'error' => null,
            'sent_at' => now(),
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'attempts' => $this->attempts + 1,
            'error' => mb_substr($error, 0, 1000),
        ])->save();
    }
}
