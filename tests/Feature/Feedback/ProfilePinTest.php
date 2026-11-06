<?php

namespace Tests\Feature\Feedback;

use App\Enums\Ad\AdApproval;
use App\Enums\Ad\AdStatus;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Models\Ad;
use App\Models\Block;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Settings\GuestSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class ProfilePinTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers {
        DatabaseTransactions::beginDatabaseTransaction as beginIsolatedDatabaseTransaction;
        CreatesUsers::makeUser as baseMakeUser;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.app_key.enabled' => false]);
        Http::preventStrayRequests();
    }

    public function beginDatabaseTransaction()
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());

        $this->beginIsolatedDatabaseTransaction();
    }

    protected function makeUser(array $overrides = []): User
    {
        return $this->baseMakeUser(array_merge([
            'status' => UserStatus::ACTIVE->value,
        ], $overrides));
    }

    private function makePost(User $user, array $overrides = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'content' => 'Profile pin test post '.uniqid(),
            'type' => PostType::TEXT,
            'status' => PostStatus::ACTIVE,
        ], $overrides));
    }

    private function pin(User $user, Post $post, bool $pinned = true)
    {
        return $this->actingAs($user, 'sanctum')->putJson(
            '/api/profile/profile/posts/'.$post->id.'/pin',
            ['pinned' => $pinned]
        );
    }

    private function profilePosts(User $viewer, User $profile, string $type = 'posts', int $cursor = 0)
    {
        return $this->actingAs($viewer, 'sanctum')->getJson('/api/profile/profile/posts?'.http_build_query([
            'id' => $profile->id,
            'filter' => [
                'type' => $type,
                'cursor' => $cursor,
            ],
        ]));
    }

    private function ids(array $posts): array
    {
        return array_map(static fn (array $post): int => (int) $post['id'], $posts);
    }

    private function makeMedia(Post $post, array $overrides = []): Media
    {
        return $post->media()->create(array_merge([
            'source_path' => 'profile-pin-test/'.uniqid().'.jpg',
            'disk' => 'public',
            'type' => MediaType::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'metadata' => [],
        ], $overrides));
    }

    public function test_owner_can_pin_three_active_posts_and_fourth_pin_is_rejected(): void
    {
        $owner = $this->makeUser();
        $posts = [
            $this->makePost($owner),
            $this->makePost($owner),
            $this->makePost($owner),
            $this->makePost($owner),
        ];

        foreach (array_slice($posts, 0, 3) as $post) {
            $this->pin($owner, $post)->assertOk()
                ->assertJsonPath('data.profile_pinned', true);
        }

        $this->pin($owner, $posts[3])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pinned');

        $this->assertSame(3, Post::where('user_id', $owner->id)->where('profile_pinned', true)->count());
        $this->assertFalse((bool) $posts[3]->refresh()->profile_pinned);
    }

    public function test_pin_is_idempotent_and_unpin_then_repin_sets_a_new_timestamp(): void
    {
        $owner = $this->makeUser();
        $post = $this->makePost($owner);
        $firstPinnedAt = Carbon::parse('2026-11-06 10:00:00');
        $secondPinnedAt = Carbon::parse('2026-11-06 10:02:00');

        $this->travelTo($firstPinnedAt);
        $this->pin($owner, $post)->assertOk();
        $storedFirstTimestamp = $post->refresh()->profile_pinned_at->getTimestamp();

        $this->travelTo($secondPinnedAt);
        $this->pin($owner, $post)->assertOk();
        $this->assertSame($storedFirstTimestamp, $post->refresh()->profile_pinned_at->getTimestamp());

        $this->pin($owner, $post, false)->assertOk()
            ->assertJsonPath('data.profile_pinned', false)
            ->assertJsonPath('data.profile_pinned_at', null);
        $this->assertNull($post->refresh()->profile_pinned_at);

        $this->travelTo($secondPinnedAt->copy()->addMinute());
        $this->pin($owner, $post)->assertOk();
        $this->assertSame(
            $secondPinnedAt->copy()->addMinute()->getTimestamp(),
            $post->refresh()->profile_pinned_at->getTimestamp()
        );
    }

    public function test_pin_requires_ownership_even_for_root_users(): void
    {
        $root = $this->makeUser([
            'role' => UserRole::ROOT->value,
        ]);
        $other = $this->makeUser();
        $post = $this->makePost($other);

        $this->pin($root, $post)->assertForbidden();
        $this->assertFalse((bool) $post->refresh()->profile_pinned);
    }

    public function test_inactive_posts_and_ad_shadow_posts_cannot_be_pinned(): void
    {
        $owner = $this->makeUser();
        $inactive = $this->makePost($owner, ['status' => PostStatus::DRAFT]);

        $this->pin($owner, $inactive)
            ->assertStatus(422)
            ->assertJsonValidationErrors('pinned');

        $ad = Ad::create([
            'user_id' => $owner->id,
            'title' => 'Profile pin test ad',
            'content' => 'Profile pin test ad content',
            'status' => AdStatus::PUBLISHED,
            'approval' => AdApproval::APPROVED,
            'total_budget' => 100,
            'spent_budget' => 0,
            'price_per_view' => 0.01,
        ]);
        $adPost = $this->makePost($owner, ['ad_id' => $ad->id]);

        $this->pin($owner, $adPost)->assertForbidden();
        $this->assertFalse((bool) $adPost->refresh()->profile_pinned);
    }

    public function test_pin_requires_a_boolean_input(): void
    {
        $owner = $this->makeUser();
        $post = $this->makePost($owner);

        $this->actingAs($owner, 'sanctum')
            ->putJson('/api/profile/profile/posts/'.$post->id.'/pin', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pinned');
    }

    public function test_guest_cannot_change_profile_pins(): void
    {
        $owner = $this->makeUser();
        $post = $this->makePost($owner);

        $this->putJson('/api/profile/profile/posts/'.$post->id.'/pin', ['pinned' => true])
            ->assertUnauthorized();
    }

    public function test_status_change_clears_profile_pin_state(): void
    {
        $owner = $this->makeUser();
        $post = $this->makePost($owner);

        $this->pin($owner, $post)->assertOk();
        $post->update(['status' => PostStatus::DRAFT]);

        $post->refresh();
        $this->assertFalse((bool) $post->profile_pinned);
        $this->assertNull($post->profile_pinned_at);
    }

    public function test_deleting_a_pinned_post_removes_it_from_the_profile(): void
    {
        $owner = $this->makeUser();
        $post = $this->makePost($owner);

        $this->pin($owner, $post)->assertOk();
        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/timeline/post/delete', ['id' => $post->id])
            ->assertOk();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->profilePosts($owner, $owner)
            ->assertOk()
            ->assertJsonPath('pinned', []);
    }

    public function test_initial_profile_posts_order_pins_by_pin_timestamp_and_excludes_them_from_normal_posts(): void
    {
        $owner = $this->makeUser();
        $oldest = $this->makePost($owner);
        $middle = $this->makePost($owner);
        $newest = $this->makePost($owner);
        $normal = $this->makePost($owner);

        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
        $this->pin($owner, $oldest)->assertOk();
        $this->travelTo(Carbon::parse('2026-11-06 10:02:00'));
        $this->pin($owner, $newest)->assertOk();
        $this->travelTo(Carbon::parse('2026-11-06 10:01:00'));
        $this->pin($owner, $middle)->assertOk();

        $response = $this->profilePosts($owner, $owner)->assertOk();
        $this->assertSame([$newest->id, $middle->id, $oldest->id], $this->ids($response->json('pinned')));
        $this->assertSame([$normal->id], $this->ids($response->json('data')));
    }

    public function test_profile_cursor_pages_exclude_pins_older_and_newer_than_the_cursor_without_duplicates(): void
    {
        config(['post.paginate_per' => 2]);
        $owner = $this->makeUser();
        $olderPinned = $this->makePost($owner);
        $normalOldest = $this->makePost($owner);
        $normalOlder = $this->makePost($owner);
        $normalNewer = $this->makePost($owner);
        $newerPinned = $this->makePost($owner);

        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
        $this->pin($owner, $olderPinned)->assertOk();
        $this->travelTo(Carbon::parse('2026-11-06 10:01:00'));
        $this->pin($owner, $newerPinned)->assertOk();

        $firstPage = $this->profilePosts($owner, $owner)->assertOk();
        $firstIds = $this->ids($firstPage->json('data'));
        $this->assertSame([$normalNewer->id, $normalOlder->id], $firstIds);
        $this->assertSame([$newerPinned->id, $olderPinned->id], $this->ids($firstPage->json('pinned')));
        $cursor = (int) $firstPage->json('meta.next_cursor');

        $secondPage = $this->profilePosts($owner, $owner, 'posts', $cursor)->assertOk();
        $secondIds = $this->ids($secondPage->json('data'));
        $this->assertSame([$normalOldest->id], $secondIds);
        $this->assertSame([], $secondPage->json('pinned'));
        $this->assertSame([], array_values(array_intersect($firstIds, $secondIds)));
    }

    public function test_profile_with_only_pins_has_no_normal_posts_and_no_more_pages(): void
    {
        $owner = $this->makeUser();
        $posts = [
            $this->makePost($owner),
            $this->makePost($owner),
            $this->makePost($owner),
        ];

        foreach ($posts as $post) {
            $this->pin($owner, $post)->assertOk();
        }

        $response = $this->profilePosts($owner, $owner)->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(3, count($response->json('pinned')));
        $this->assertFalse($response->json('meta.has_more'));
        $this->assertNull($response->json('meta.next_cursor'));
    }

    public function test_media_profile_keeps_pinned_posts_in_id_order_and_includes_media(): void
    {
        config(['post.paginate_per' => 10]);
        $owner = $this->makeUser();
        $older = $this->makePost($owner, ['type' => PostType::IMAGE]);
        $newer = $this->makePost($owner, ['type' => PostType::IMAGE]);
        $normal = $this->makePost($owner, ['type' => PostType::IMAGE]);
        $this->makeMedia($older, ['source_path' => 'profile-pin-test/older.jpg']);
        $this->makeMedia($newer, ['source_path' => 'profile-pin-test/newer.jpg']);
        $this->makeMedia($normal, ['source_path' => 'profile-pin-test/normal.jpg']);

        $this->travelTo(Carbon::parse('2026-11-06 10:00:00'));
        $this->pin($owner, $older)->assertOk();
        $this->travelTo(Carbon::parse('2026-11-06 10:01:00'));
        $this->pin($owner, $newer)->assertOk();

        $response = $this->profilePosts($owner, $owner, 'media')->assertOk();
        $posts = $response->json('data');
        $this->assertSame([$normal->id, $newer->id, $older->id], $this->ids($posts));
        $this->assertNotEmpty($posts[1]['relations']['media']);
        $this->assertNotEmpty($posts[2]['relations']['media']);
        $this->assertSame([], $response->json('pinned'));
    }

    public function test_blocked_profile_returns_no_posts_or_pins(): void
    {
        $viewer = $this->makeUser();
        $owner = $this->makeUser();
        $post = $this->makePost($owner);
        $this->pin($owner, $post)->assertOk();
        Block::create(['blocker_id' => $viewer->id, 'blocked_id' => $owner->id]);

        $this->profilePosts($viewer, $owner)
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('pinned', [])
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_inactive_profile_author_is_not_found(): void
    {
        $viewer = $this->makeUser();
        $owner = $this->makeUser(['status' => UserStatus::SUSPENDED->value]);
        $post = $this->makePost($owner);
        $post->forceFill(['profile_pinned' => true, 'profile_pinned_at' => now()])->saveQuietly();

        $this->profilePosts($viewer, $owner)->assertNotFound();
    }

    public function test_guest_profile_posts_are_sensitive_safe_and_pins_are_separate(): void
    {
        $settings = app(GuestSettings::class);
        $settings->enabled = true;
        $settings->save();

        $owner = $this->makeUser();
        $visible = $this->makePost($owner, ['content' => 'GUEST_VISIBLE']);
        $sensitive = $this->makePost($owner, [
            'content' => 'GUEST_SENSITIVE',
            'is_sensitive' => true,
        ]);
        $this->makePost($owner, ['content' => 'GUEST_NORMAL']);
        $this->pin($owner, $visible)->assertOk();
        $this->pin($owner, $sensitive)->assertStatus(422);

        $response = $this->getJson('/api/guest/v1/profile/'.$owner->username.'/posts?filter[type]=posts')
            ->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('GUEST_VISIBLE', $content);
        $this->assertStringContainsString('GUEST_NORMAL', $content);
        $this->assertStringNotContainsString('GUEST_SENSITIVE', $content);
        $this->assertSame([$visible->id], $this->ids($response->json('pinned')));
        $this->assertNotContains($visible->id, $this->ids($response->json('data')));
        $this->assertFalse($response->json('pinned.0.meta.permissions.can_pin'));
    }

    public function test_guest_profile_posts_hide_inactive_authors(): void
    {
        $settings = app(GuestSettings::class);
        $settings->enabled = true;
        $settings->save();

        $owner = $this->makeUser();
        $visible = $this->makePost($owner, ['content' => 'ACTIVE_AUTHOR_POST']);
        $owner->update(['status' => UserStatus::SUSPENDED]);

        $response = $this->getJson('/api/guest/v1/profile/'.$owner->username.'/posts')
            ->assertNotFound();
        $this->assertStringNotContainsString($visible->content, $response->getContent());
    }
}
