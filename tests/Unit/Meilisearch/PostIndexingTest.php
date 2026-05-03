<?php

namespace Tests\Unit\Meilisearch;

use Tests\TestCase;
use App\Models\Post;
use App\Models\User;
use App\Enums\Post\PostType;
use App\Enums\Post\PostStatus;
use App\Actions\Post\IndexPostAction;
use App\Actions\Post\DeindexPostAction;
use App\Services\Meilisearch\MeilisearchService;

class PostIndexingTest extends TestCase
{
    private MeilisearchService $meilisearch;

    private User $user;

    private array $createdPostIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Post::unsetEventDispatcher();

        $this->user = User::factory()->create([
            'username' => 'indexing_user_' . uniqid(),
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
            usleep(200000);
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

    public function test_index_post_action_adds_document(): void
    {
        $post = $this->createAndIndexPost(content: 'Index this post please');

        if ($this->meilisearch->isAvailable()) {
            usleep(500000);

            $results = $this->meilisearch->search(
                indexName: 'posts',
                query: 'Index',
                options: ['limit' => 5],
            );

            $this->assertNotNull($results);
            $this->assertNotEmpty($results['hits'], 'MeiliSearch should return hits for recently indexed document');

            $ids = collect($results['hits'])->pluck('id')->toArray();
            $this->assertContains($post->id, $ids);
        } else {
            $this->markTestSkipped('MeiliSearch service is not available.');
        }
    }

    public function test_updated_post_is_reindexed(): void
    {
        $post = $this->createAndIndexPost(content: 'Original content');

        usleep(500000);

        $post->update(['content' => 'Updated content different']);
        $post->refresh();
        $post->load('user');

        (new IndexPostAction(post: $post))->execute();

        if ($this->meilisearch->isAvailable()) {
            usleep(500000);

            $results = $this->meilisearch->search(
                indexName: 'posts',
                query: 'different',
                options: ['limit' => 5],
            );

            $this->assertNotNull($results);
            $this->assertNotEmpty($results['hits'], 'MeiliSearch should find the updated content');
        } else {
            $this->markTestSkipped('MeiliSearch service is not available.');
        }
    }

    public function test_deindex_post_action_removes_document(): void
    {
        $post = $this->createAndIndexPost(content: 'This will be deleted');

        usleep(500000);

        (new DeindexPostAction(post: $post))->execute();

        if ($this->meilisearch->isAvailable()) {
            usleep(500000);

            $results = $this->meilisearch->search(
                indexName: 'posts',
                query: 'This will be deleted',
                options: ['limit' => 5],
            );

            $this->assertNotNull($results);

            $ids = collect($results['hits'])->pluck('id')->toArray();
            $this->assertNotContains($post->id, $ids);
        } else {
            $this->markTestSkipped('MeiliSearch service is not available.');
        }
    }

    public function test_index_post_action_strips_html_tags(): void
    {
        $post = $this->createAndIndexPost(content: '<p><strong>Bold</strong> and <em>italic</em> text</p>');

        if ($this->meilisearch->isAvailable()) {
            usleep(500000);

            $results = $this->meilisearch->search(
                indexName: 'posts',
                query: 'Bold and italic',
                options: ['limit' => 5],
            );

            $this->assertNotNull($results);
            $this->assertNotEmpty($results['hits'], 'MeiliSearch should find content after HTML tags are stripped');
        } else {
            $this->markTestSkipped('MeiliSearch service is not available.');
        }
    }
}
