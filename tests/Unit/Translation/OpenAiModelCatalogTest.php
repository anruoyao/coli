<?php

namespace Tests\Unit\Translation;

use App\Services\Translation\OpenAiModelCatalog;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiModelCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_loads_and_deduplicates_models_from_the_openai_v1_endpoint(): void
    {
        Http::fake(['relay.test/*' => Http::response([
            'data' => [
                ['id' => 'gpt-4o'],
                ['id' => 'gpt-4o'],
                ['id' => 'claude-3'],
                ['object' => 'model'],
            ],
        ])]);

        $models = app(OpenAiModelCatalog::class)->list(
            'https://relay.test',
            'secret-test-key',
            15,
            2,
        );

        $this->assertSame(['gpt-4o', 'claude-3'], $models);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://relay.test/v1/models'
                && $request->hasHeader('Authorization', 'Bearer secret-test-key');
        });
    }

    public function test_does_not_duplicate_v1_when_the_api_url_already_contains_it(): void
    {
        Http::fake(['relay.test/*' => Http::response(['data' => [['id' => 'gpt-4o']]])]);

        app(OpenAiModelCatalog::class)->list('https://relay.test/v1/chat/completions', '', 15, 2);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://relay.test/v1/models');
    }

    public function test_returns_an_empty_list_for_a_valid_empty_response(): void
    {
        Http::fake(['relay.test/*' => Http::response(['data' => []])]);

        $models = app(OpenAiModelCatalog::class)->list('https://relay.test/v1', '', 15, 2);

        $this->assertSame([], $models);
    }

    public function test_rejects_an_invalid_models_response(): void
    {
        Http::fake(['relay.test/*' => Http::response(['models' => []])]);

        $this->expectException(RuntimeException::class);
        app(OpenAiModelCatalog::class)->list('https://relay.test/v1', '', 15, 2);
    }

    public function test_rejects_failed_provider_responses(): void
    {
        Http::fake(['relay.test/*' => Http::response(['error' => 'Unauthorized'], 401)]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);
        app(OpenAiModelCatalog::class)->list('https://relay.test/v1', '', 15, 2);
    }
}
