<?php

namespace Tests\Unit\Translation;

use App\Http\Controllers\Api\User\Translator\TranslatorController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TranslationRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.translation.service' => 'nllb',
            'services.translation.enabled' => true,
            'services.translation.api_url' => 'http://translation.test',
            'services.translation.max_text_length' => 10,
        ]);
        Route::post('/test-translation', [TranslatorController::class, 'translate']);
        Http::preventStrayRequests();
    }

    public function test_invalid_input_never_calls_inference(): void
    {
        $this->postJson('/test-translation', ['text' => '你好', 'target' => 'unknown'])->assertUnprocessable();
        $this->postJson('/test-translation', ['text' => str_repeat('好', 11)])->assertUnprocessable();
        $this->postJson('/test-translation', ['text' => '   '])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_explicit_languages_are_mapped_and_success_envelope_is_preserved(): void
    {
        Http::fake(['translation.test/translate' => Http::response(['translated_text' => '你好'])]);
        $this->postJson('/test-translation', ['text' => 'Hello', 'source' => 'en', 'target' => 'zh'])
            ->assertOk()->assertJsonPath('data.translated_text', '你好')->assertJsonPath('data.source_language', 'en');
        Http::assertSent(fn ($request) => $request['source'] === 'eng_Latn' && $request['target'] === 'zho_Hans');
    }

    public function test_text_only_clients_detect_chinese_and_use_configured_target(): void
    {
        config(['services.translation.default_source' => 'auto', 'services.translation.default_target' => 'en', 'services.translation.max_text_length' => 100]);
        Http::fake(['translation.test/translate' => Http::response(['translated_text' => 'Hello'])]);
        $this->postJson('/test-translation', ['text' => '你好，这是中文翻译测试。'])
            ->assertOk()->assertJsonPath('data.translated_text', 'Hello')->assertJsonPath('data.source_language', 'zh');
        Http::assertSent(fn ($request) => $request['source'] === 'zho_Hans' && $request['target'] === 'eng_Latn');
    }

    public function test_disabled_translation_is_controlled_and_does_not_call_provider(): void
    {
        config(['services.translation.enabled' => false]);
        $this->postJson('/test-translation', ['text' => 'Hello'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_upstream_busy_returns_retryable_status_without_provider_details(): void
    {
        Http::fake(['translation.test/translate' => Http::response(['detail' => 'private internal detail'], 429)]);
        $this->postJson('/test-translation', ['text' => 'Hello', 'source' => 'en', 'target' => 'zh'])
            ->assertStatus(429)->assertHeader('Retry-After', '5')->assertDontSee('private internal detail');
    }
}
