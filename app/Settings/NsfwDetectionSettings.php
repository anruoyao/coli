<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class NsfwDetectionSettings extends Settings
{
    // NSFW 自动识别总开关（关闭后新帖不再自动检测）
    public bool $enabled;

    // 置信度阈值：检测标签分数 >= 该值才判定命中
    public float $threshold;

    // 触发标签集：仅这些 NudeNet 标签参与判定（COVERED_* / BELLY / FEET / FACE 等不触发）
    public array $trigger_labels;

    // 命中后是否通知作者（站内 + 邮件）
    public bool $notify_author;

    public static function group(): string
    {
        return 'nsfw_detection';
    }

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'threshold' => 0.60,
            // NudeNet 3.x 真实标签命名（X_EXPOSED 风格，勿用 2.x 的 EXPOSED_X 风格）
            'trigger_labels' => [
                'FEMALE_GENITALIA_EXPOSED',
                'MALE_GENITALIA_EXPOSED',
                'FEMALE_BREAST_EXPOSED',
                'MALE_BREAST_EXPOSED',
                'BUTTOCKS_EXPOSED',
                'ANUS_EXPOSED',
            ],
            'notify_author' => true,
        ];
    }
}
