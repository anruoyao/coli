<?php

namespace Tests\Feature\Translation;

use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\UserStatus;
use App\Http\Controllers\Api\User\Translator\TranslatorController;
use App\Models\Block;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class PostTranslationTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private User $viewer;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.translation.service' => 'nllb',
            'services.translation.enabled' => true,
            'services.translation.api_url' => 'http://translation.test',
            'services.translation.max_text_length' => 2000,
            'cache.default' => 'array',
        ]);
        Route::post('/test-post-translation', [TranslatorController::class, 'translate'])->middleware('auth:sanctum');
        $author = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $this->viewer = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $this->post = Post::create([
            'user_id' => $author->id,
            'content' => '你好，世界',
            'status' => PostStatus::ACTIVE,
            'type' => PostType::TEXT,
        ]);
        $this->actingAs($this->viewer, 'sanctum');
        Http::preventStrayRequests();
        Http::fake(['translation.test/translate' => Http::response(['translated_text' => 'Hello world'])]);
    }

    private function translate(array $payload = [])
    {
        return $this->postJson('/test-post-translation', array_merge(['post_id' => $this->post->id, 'target' => 'en'], $payload));
    }

    public function test_result_is_shared_across_users_and_target_aliases(): void
    {
        $this->translate(['text' => 'forged client text', 'source' => 'ru'])->assertOk();
        Http::assertSent(fn ($request) => $request['text'] === $this->post->content && $request['source'] === 'zho_Hans');
        $other = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $this->actingAs($other, 'sanctum');
        Cache::flush();
        $this->translate(['target' => 'en-us'])->assertOk()->assertJsonPath('data.translated_text', 'Hello world');
        Http::assertSentCount(1);
        $this->assertSame(1, PostTranslation::where('post_id', $this->post->id)->count());
    }

    public function test_each_target_has_its_own_result(): void
    {
        $this->translate()->assertOk();
        $this->translate(['target' => 'fr'])->assertOk();
        $this->translate(['target' => 'fr'])->assertOk();
        Http::assertSentCount(2);
        $this->assertSame(2, PostTranslation::where('post_id', $this->post->id)->count());
    }

    public function test_edit_invalidates_all_languages_and_bulk_edits_cannot_return_stale_text(): void
    {
        $this->translate()->assertOk();
        $this->post->update(['content' => '新的正文']);
        $this->assertSame(0, PostTranslation::where('post_id', $this->post->id)->count());
        $this->translate()->assertOk();
        Post::whereKey($this->post->id)->update(['content' => '再次修改']);
        $this->translate()->assertOk();
        Http::assertSentCount(3);
    }

    public function test_deleting_a_post_cascades_and_status_deletion_purges_results(): void
    {
        $this->translate()->assertOk();
        $this->post->update(['status' => PostStatus::DELETED]);
        $this->assertSame(0, PostTranslation::where('post_id', $this->post->id)->count());
        $this->translate()->assertNotFound();
        $this->post->update(['status' => PostStatus::ACTIVE]);
        $this->translate()->assertOk();
        Post::whereKey($this->post->id)->delete();
        $this->assertSame(0, PostTranslation::where('post_id', $this->post->id)->count());
        $this->translate()->assertNotFound();
    }

    public function test_cached_text_is_denied_to_blocked_viewers_and_inactive_authors(): void
    {
        $this->translate()->assertOk();
        $block = Block::create(['blocker_id' => $this->post->user_id, 'blocked_id' => $this->viewer->id]);
        $this->translate()->assertNotFound();
        $block->delete();
        $block = Block::create(['blocker_id' => $this->viewer->id, 'blocked_id' => $this->post->user_id]);
        $this->translate()->assertNotFound();
        $block->delete();
        $this->post->user->update(['status' => UserStatus::ONBOARDING]);
        $this->translate()->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_failed_inference_is_not_cached_and_releases_lock(): void
    {
        Http::fake(['translation.test/translate' => Http::response([], 503)]);
        $this->translate()->assertStatus(503);
        $this->assertSame(0, PostTranslation::where('post_id', $this->post->id)->count());
        Http::fake(['translation.test/translate' => Http::response(['translated_text' => 'Hello'])]);
        $this->translate()->assertOk();
    }

    public function test_concurrent_request_does_not_call_model(): void
    {
        $lock = Cache::lock("post-translation:{$this->post->id}:en", 60);
        $lock->get();
        try {
            $this->translate()->assertStatus(429);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
        $this->translate()->assertOk();
    }

    public function test_post_changed_or_deleted_during_inference_is_not_saved(): void
    {
        Http::fake(function () {
            $this->post->update(['content' => '修改后的内容']);
            return Http::response(['translated_text' => 'Outdated']);
        });
        $this->translate()->assertStatus(409);
        $this->assertSame(0, PostTranslation::where('post_id', $this->post->id)->count());
        Http::fake(function () {
            $this->post->delete();
            return Http::response(['translated_text' => 'Deleted']);
        });
        $this->translate()->assertNotFound();
        $this->assertSame(0, PostTranslation::where('post_id', $this->post->id)->count());
    }

    public function test_post_translation_requires_authentication(): void
    {
        auth('sanctum')->forgetUser();
        auth('web')->forgetUser();
        $this->translate()->assertUnauthorized();
        Http::assertNothingSent();
    }
}
