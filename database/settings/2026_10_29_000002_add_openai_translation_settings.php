<?php

use App\Settings\TranslationSettings;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $defaults = TranslationSettings::defaults();

            foreach (['model', 'system_prompt', 'pretranslate_enabled', 'openai_concurrency'] as $name) {
                if ($this->migrator->exists("translation.{$name}")) {
                    continue;
                }

                $this->migrator->add("translation.{$name}", $defaults[$name]);
            }
        });
    }
};
