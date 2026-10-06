<?php

namespace App\Enums\Media;

enum MediaMigrationStatus: string
{
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isRunning(): bool
    {
        return $this == self::RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this == self::COMPLETED;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }
}
