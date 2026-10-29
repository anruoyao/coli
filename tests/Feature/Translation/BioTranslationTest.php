<?php

namespace Tests\Feature\Translation;

use App\Enums\User\UserStatus;
use App\Http\Controllers\Api\User\Translator\TranslatorController;
use App\Models\BioTranslation;
use App\Models\Block;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class BioTranslationTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private User $viewer;
    private User $profile;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.translation.service' => 'nllb',
            'services.translation.enabled' => true,
            'services.translation.api_url' => 'http://translation.test',
            'services.translation.max_text_length' => 2000,
            'services.translation.lock_store' => 'array',
            'cache.default' => 'array',
        ]);
        Route::post('/test-bio-translation', [TranslatorController::class, 'translate'])->middleware('auth:sanctum');
        $this->profile = $this->makeUser(['status' => UserStatus::ACTIVE, 'bio' => '你好，世界']);
        $this->viewer = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $this->actingAs($this->viewer, 'sanctum');
        Http::preventStrayRequests();
        Http::fake(['translation.test/translate' => Http::response(['translated_text' => 'Hello world'])]);
    }

    private function translate(array $payload = [])
    {
        return $this->postJson('/test-bio-translation', array_merge([
            'profile_id' => $this->profile->id,
            'target' => 'en',
        ], $payload));
    }

    public function test_result_is_shared_across_users_and_target_aliases(): void
    {
        $this->translate(['text' => 'forged client text', 'source' => 'ru'])
            ->assertOk()
            ->assertJsonPath('data.translated_text', 'Hello world');
        Http::assertSent(fn ($request) => $request['text'] === $this->profile->bio && $request['source'] === 'zho_Hans');

        $other = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $this->actingAs($other, 'sanctum');
        Cache::flush();
        $this->translate(['target' => 'en-us'])
            ->assertOk()
            ->assertJsonPath('data.translated_text', 'Hello world');

        Http::assertSentCount(1);
        $this->assertSame(1, BioTranslation::where('user_id', $this->profile->id)->count());
    }

    public function test_each_target_has_its_own_result_and_bio_updates_invalidate_cache(): void
    {
        $this->translate()->assertOk();
        $this->translate(['target' => 'fr'])->assertOk();
        $this->assertSame(2, BioTranslation::where('user_id', $this->profile->id)->count());

        $this->profile->update(['bio' => '新的简介']);
        $this->assertSame(0, BioTranslation::where('user_id', $this->profile->id)->count());
        $this->translate()->assertOk();
        Http::assertSentCount(3);
    }

    public function test_bulk_bio_update_does_not_return_translation_for_old_content_hash(): void
    {
        $this->translate()->assertOk();
        User::whereKey($this->profile->id)->update(['bio' => '通过批量更新修改']);

        $this->translate()->assertOk();
        Http::assertSentCount(2);
    }

    public function test_cached_translation_is_denied_after_block_or_profile_deactivation(): void
    {
        $this->translate()->assertOk();
        $block = Block::create(['blocker_id' => $this->profile->id, 'blocked_id' => $this->viewer->id]);
        $this->translate()->assertNotFound();
        $block->delete();

        Block::create(['blocker_id' => $this->viewer->id, 'blocked_id' => $this->profile->id]);
        $this->translate()->assertNotFound();
        Block::where('blocker_id', $this->viewer->id)->where('blocked_id', $this->profile->id)->delete();

        $this->profile->update(['status' => UserStatus::ONBOARDING]);
        $this->translate()->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_concurrent_request_does_not_call_provider(): void
    {
        $lock = Cache::lock("bio-translation:{$this->profile->id}:en", 60);
        $lock->get();
        try {
            $this->translate()->assertStatus(429);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_profile_changed_during_inference_is_not_saved(): void
    {
        Http::fake(function () {
            $this->profile->update(['bio' => '并发更新后的简介']);

            return Http::response(['translated_text' => 'Outdated']);
        });

        $this->translate()->assertStatus(409);
        $this->assertSame(0, BioTranslation::where('user_id', $this->profile->id)->count());
    }

    public function test_profile_translation_requires_authentication(): void
    {
        auth('sanctum')->forgetUser();
        auth('web')->forgetUser();

        $this->translate()->assertUnauthorized();
        Http::assertNothingSent();
    }
}
