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
                'EXPOSED_GENITALIA_F',
                'EXPOSED_GENITALIA_M',
                'FEMALE_BREAST_EXPOSED',
                'BUTTOCKS_EXPOSED',
            ]);
            $this->migrator->add('nsfw_detection.notify_author', true);
        });
    }
};
