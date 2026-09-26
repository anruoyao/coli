<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('upload.image_max_mb', 128);
        $this->migrator->add('upload.video_max_mb', 512);
        $this->migrator->add('upload.audio_max_mb', 24);
        $this->migrator->add('upload.gif_max_mb', 2);
        $this->migrator->add('upload.document_max_mb', 24);
    }
};
