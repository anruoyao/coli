<?php

namespace Tests\Unit\Translation;

use App\Jobs\User\Translation\PreparePostTranslations;
use App\Jobs\User\Translation\TranslatePost;
use App\Services\Translation\PostTranslationService;
use App\Services\Translation\TranslationConfigRefresher;
use App\Services\Translation\TranslationLanguageCatalog;
use App\Services\Translation\TranslationLanguageDetector;
use App\Settings\TranslationSettings;
use Mockery;
use Tests\TestCase;

class TranslationConfigRefresherTest extends TestCase
{
    public function test_applies_persisted_translation_settings_to_runtime_config(): void
    {
        $settings = new TranslationSettings([
            'enabled' => true,
            'driver' => 'openai',
            'api_url' => 'https://translation.test/v1',
            'api_key' => 'test-key',
            'default_source' => 'auto',
            'default_target' => 'fr',
            'timeout' => 30,
            'connect_timeout' => 4,
            'max_text_length' => 1800,
            'threads' => 3,
            'compute_type' => 'int8',
            'beam_size' => 2,
            'max_input_tokens' => 400,
            'max_output_tokens' => 450,
            'max_concurrent_requests' => 2,
            'global_rate_per_minute' => 80,
            'rate_per_minute' => 90,
            'model' => 'current-model',
            'system_prompt' => 'Translate faithfully.',
            'pretranslate_enabled' => true,
            'openai_concurrency' => 3,
        ]);

        app(TranslationConfigRefresher::class)->apply($settings);

        $this->assertSame('openai', config('services.translation.service'));
        $this->assertSame('current-model', config('services.translation.model'));
        $this->assertSame('test-key', config('services.translation.api_key'));
        $this->assertSame('https://translation.test/v1', config('services.translation.api_url'));
        $this->assertSame('fr', config('services.translation.default_target'));
        $this->assertTrue(config('services.translation.pretranslate_enabled'));
        $this->assertSame(90, config('security.rate_limits.translator.max'));
    }

    public function test_prepare_job_refreshes_runtime_config_before_checking_settings(): void
    {
        $refresher = Mockery::mock(TranslationConfigRefresher::class);
        $refresher->shouldReceive('refresh')->once()->andReturnUsing(function (): void {
            config(['services.translation.enabled' => false]);
        });

        (new PreparePostTranslations(1))->handle(
            Mockery::mock(TranslationLanguageCatalog::class),
            Mockery::mock(TranslationLanguageDetector::class),
            $refresher,
        );

        $this->assertFalse(config('services.translation.enabled'));
    }

    public function test_translate_job_refreshes_runtime_config_before_checking_driver(): void
    {
        $refresher = Mockery::mock(TranslationConfigRefresher::class);
        $refresher->shouldReceive('refresh')->once()->andReturnUsing(function (): void {
            config(['services.translation.service' => 'nllb']);
        });

        (new TranslatePost(1, str_repeat('a', 64), 'en'))->handle(
            Mockery::mock(PostTranslationService::class),
            $refresher,
        );

        $this->assertSame('nllb', config('services.translation.service'));
    }
}
