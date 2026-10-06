<?php

namespace App\Models;

use App\Enums\Media\MediaMigrationStatus;
use App\Enums\Media\MediaMigrationType;
use Illuminate\Database\Eloquent\Model;

class MediaMigration extends Model
{
    public $guarded = [];

    public $casts = [
        'type' => MediaMigrationType::class,
        'status' => MediaMigrationStatus::class,
        'options' => 'array',
        'report' => 'array',
        'files_total' => 'integer',
        'files_done' => 'integer',
        'files_failed' => 'integer',
        'bytes_total' => 'integer',
        'bytes_done' => 'integer',
        'verify_missing' => 'integer',
        'verify_mismatch' => 'integer',
        'cursor' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * 进度百分比（0-100，按字节数计算）
     */
    public function getProgressPercentAttribute(): int
    {
        if ($this->bytes_total <= 0) {
            return $this->status->isCompleted() ? 100 : 0;
        }

        return (int) min(100, floor($this->bytes_done / $this->bytes_total * 100));
    }

    /**
     * 是否存在失败文件（完成后用于判断是否展示重试入口）
     */
    public function hasFailures(): bool
    {
        return $this->files_failed > 0;
    }
}
