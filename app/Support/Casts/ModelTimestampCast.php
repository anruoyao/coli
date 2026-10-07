<?php

namespace App\Support\Casts;

use App\Support\DateFormatter;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ModelTimestampCast implements CastsAttributes
{
	public function get($model, string $key, $value, array $attributes)
    {
        if (empty($value)) {
            return null;
        }

        return new DateFormatter($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof DateFormatter) {
            return $value->getTimestamp();
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        // Livewire 组件水合模型时会回传 ISO 8601 格式（如 2026-10-07T07:24:16.000000Z），
        // 直接入库会触发 MySQL 1292 Invalid datetime；需规范化为 datetime 可接受格式，
        // 并转回应用时区，避免 created_at 等时间戳被重写时产生偏移。
        if (is_string($value) && ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return Carbon::parse($value)
                ->setTimezone(config('app.timezone'))
                ->format('Y-m-d H:i:s');
        }

        return $value;
    }
}
