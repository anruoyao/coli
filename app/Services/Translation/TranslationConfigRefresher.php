<?php

namespace App\Services\Translation;

use App\Settings\TranslationSettings;

class TranslationConfigRefresher
{
    public function refresh(): void
    {
        $this->apply(app(TranslationSettings::class)->refresh());
    }

    public function apply(TranslationSettings $settings): void
    {
        config([
            'services.translation.enabled' => $settings->enabled,
            'services.translation.service' => $settings->driver,
            'services.translation.api_url' => $settings->api_url,
            'services.translation.api_key' => $settings->api_key,
            'services.translation.default_source' => $settings->default_source,
            'services.translation.default_target' => $settings->default_target,
            'services.translation.timeout' => $settings->timeout,
            'services.translation.connect_timeout' => $settings->connect_timeout,
            'services.translation.max_text_length' => $settings->max_text_length,
            'services.translation.threads' => $settings->threads,
            'services.translation.compute_type' => $settings->compute_type,
            'services.translation.beam_size' => $settings->beam_size,
            'services.translation.max_input_tokens' => $settings->max_input_tokens,
            'services.translation.max_output_tokens' => $settings->max_output_tokens,
            'services.translation.max_concurrent_requests' => $settings->max_concurrent_requests,
            'services.translation.global_rate_per_minute' => $settings->global_rate_per_minute,
            'services.translation.model' => $settings->model,
            'services.translation.system_prompt' => $settings->system_prompt,
            'services.translation.pretranslate_enabled' => $settings->pretranslate_enabled,
            'services.translation.openai_concurrency' => $settings->openai_concurrency,
            'security.rate_limits.translator.max' => $settings->rate_per_minute,
        ]);
    }
}
