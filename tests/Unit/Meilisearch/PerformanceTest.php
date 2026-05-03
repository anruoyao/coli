<?php

namespace Tests\Unit\Meilisearch;

use Tests\TestCase;
use App\Services\Meilisearch\MeilisearchService;

class PerformanceTest extends TestCase
{
    private MeilisearchService $meilisearch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->meilisearch = app(MeilisearchService::class);
    }

    public function test_search_response_time_is_under_threshold(): void
    {
        if (! $this->meilisearch->isAvailable()) {
            $this->markTestSkipped('MeiliSearch service is not available.');
        }

        $startTime = hrtime(true);

        $results = $this->meilisearch->search(
            indexName: 'posts',
            query: 'test',
            options: ['limit' => 10],
        );

        $endTime = hrtime(true);

        $responseTimeMs = ($endTime - $startTime) / 1e6;

        $this->assertNotNull($results);
        $this->assertLessThan(
            500,
            $responseTimeMs,
            "Search response time {$responseTimeMs}ms exceeds 500ms threshold"
        );
    }

    public function test_index_configuration_is_correct(): void
    {
        if (! $this->meilisearch->isAvailable()) {
            $this->markTestSkipped('MeiliSearch service is not available.');
        }

        $index = $this->meilisearch->getIndex(indexName: 'posts');

        $this->assertNotNull($index);

        $cfg = config('meilisearch.indexes.posts');

        $this->assertContains('content', $cfg['searchable_attributes']);
        $this->assertContains('username', $cfg['searchable_attributes']);
        $this->assertContains('user_id', $cfg['filterable_attributes']);
        $this->assertContains('created_at_timestamp', $cfg['sortable_attributes']);
    }

    public function test_typo_tolerance_is_enabled(): void
    {
        if (! $this->meilisearch->isAvailable()) {
            $this->markTestSkipped('MeiliSearch service is not available.');
        }

        $cfg = config('meilisearch.indexes.posts.typo_tolerance');

        $this->assertTrue($cfg['enabled']);
        $this->assertGreaterThan(0, $cfg['min_word_size_for_typos']['one_typo']);
    }

    public function test_service_handles_unavailable_gracefully(): void
    {
        $service = $this->meilisearch;

        if ($service->isAvailable()) {
            $index = $service->getIndex(indexName: 'posts');
            $this->assertNotNull($index);
        }

        $results = $service->search(
            indexName: 'posts',
            query: '',
            options: ['limit' => 1],
        );

        if ($service->isAvailable()) {
            $this->assertNotNull($results);
        } else {
            $this->assertNull($results);
        }
    }
}
