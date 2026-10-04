<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrator->add('nsfw_detection.enabled', false);
            $this->migrator->add('nsfw_detection.threshold', 0.60);
            $this->migrator->add('nsfw_detection.trigger_labels', [
                // NudeNet 3.x 真实标签命名（X_EXPOSED 风格，勿用 2.x 的 EXPOSED_X 风格）
                'FEMALE_GENITALIA_EXPOSED',
                'MALE_GENITALIA_EXPOSED',
                'FEMALE_BREAST_EXPOSED',
                'MALE_BREAST_EXPOSED',
                'BUTTOCKS_EXPOSED',
                'ANUS_EXPOSED',
            ]);
            $this->migrator->add('nsfw_detection.notify_author', true);
        });
    }
};
