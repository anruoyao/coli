<?php

namespace Tests\Feature\Bookmarks;

use App\Enums\Post\PostStatus;
use App\Enums\User\UserStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class BookmarkOrderTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.app_key.enabled' => false]);
    }

    public function test_post_bookmarks_are_returned_with_the_most_recently_bookmarked_first(): void
    {
        $viewer = $this->makeUser(['status' => UserStatus::ACTIVE->value]);
        $author = $this->makeUser(['status' => UserStatus::ACTIVE->value]);

        $recentlyBookmarkedPost = $this->makePost($author);
        $olderBookmarkPost = $this->makePost($author);

        $olderBookmarkPost->bookmarks()->create([
            'user_id' => $viewer->id,
            'created_at' => now()->subDay(),
        ]);
        $recentlyBookmarkedPost->bookmarks()->create([
            'user_id' => $viewer->id,
            'created_at' => now(),
        ]);

        $this->actingAs($viewer, 'sanctum')
            ->postJson('/api/bookmarks/bookmarks', ['type' => 'posts'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $recentlyBookmarkedPost->id)
            ->assertJsonPath('data.1.id', $olderBookmarkPost->id);
    }

    private function makePost(User $author): Post
    {
        return Post::create([
            'user_id' => $author->id,
            'content' => 'Bookmark ordering test',
            'type' => 'text',
            'status' => PostStatus::ACTIVE->value,
        ]);
    }
}
