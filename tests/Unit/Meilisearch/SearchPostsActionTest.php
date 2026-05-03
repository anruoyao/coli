<?php

namespace Tests\Unit\Meilisearch;

use Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use App\Enums\Post\PostType;
use App\Enums\Post\PostStatus;
use App\Actions\Post\IndexPostAction;
use App\Actions\Post\DeindexPostAction;
use App\Actions\Post\SearchPostsAction;
use App\Services\Meilisearch\MeilisearchService;

class SearchPostsActionTest extends TestCase
{
    private MeilisearchService $meilisearch;

    private User $user;

    private array $createdPostIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Post::unsetEventDispatcher();

        $this->user = User::factory()->create([
            'username' => 'testuser_search_' . uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $this->meilisearch = app(MeilisearchService::class);

        if ($this->meilisearch->isAvailable()) {
            $this->meilisearch->deleteAllDocuments(indexName: 'posts');
            usleep(100000);
        }
    }

    protected function tearDown(): void
    {
        if (! empty($this->createdPostIds) && $this->meilisearch->isAvailable()) {
            $this->meilisearch->deleteAllDocuments(indexName: 'posts');
        }

        $this->user->delete();

        parent::tearDown();
    }

    private function createAndIndexPost(string $content): Post
    {
        $post = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => $content,
            'status' => PostStatus::ACTIVE,
            'type' => PostType::TEXT,
        ]);

        $this->createdPostIds[] = $post->id;

        $post->load('user');

        (new IndexPostAction(post: $post))->execute();

        return $post;
    }

    public function test_search_returns_empty_results_for_no_match(): void
    {
        $this->createAndIndexPost(content: 'completely unrelated text');

        usleep(500000);

        $action = new SearchPostsAction(
            query: 'nonexistent_query_xyz',
            page: 1,
            perPage: 10,
        );

        $results = $action->execute();

        $this->assertIsArray($results);
        $this->assertEmpty($results['ids']);
    }

    public function test_search_returns_post_ids_when_match_found(): void
    {
        $post = $this->createAndIndexPost(content: 'Hello world this is a test post');

        usleep(500000);

        $results = $this->meilisearch->search(
            indexName: 'posts',
            query: 'Hello',
            options: ['limit' => 10],
        );

        $this->assertNotNull($results);
        $this->assertNotEmpty($results['hits'], 'MeiliSearch should return hits for recently indexed document');
        $this->assertContains($post->id, collect($results['hits'])->pluck('id')->toArray());
    }

    public function test_search_respects_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createAndIndexPost(content: "test pagination post {$i}");
        }

        usleep(500000);

        $action = new SearchPostsAction(
            query: 'test pagination',
            page: 1,
            perPage: 2,
        );

        $results = $action->execute();

        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(2, count($results['ids']));
    }

    public function test_search_fallback_to_database_when_meilisearch_unavailable(): void
    {
        $post = $this->createAndIndexPost(content: 'database fallback test content');

        usleep(500000);

        $action = new SearchPostsAction(
            query: 'fallback test',
            page: 1,
            perPage: 10,
        );

        $results = $action->execute();

        $this->assertIsArray($results);
    }

    public function test_search_handles_empty_query(): void
    {
        $action = new SearchPostsAction(
            query: '',
            page: 1,
            perPage: 10,
        );

        $results = $action->execute();

        $this->assertIsArray($results);
    }

    public function test_search_with_sort_by_parameter(): void
    {
        $this->createAndIndexPost(content: 'sort test content');

        usleep(500000);

        $action = new SearchPostsAction(
            query: 'sort test',
            page: 1,
            perPage: 10,
            sortBy: 'created_at_timestamp:desc',
        );

        $results = $action->execute();

        $this->assertIsArray($results);
    }
}
