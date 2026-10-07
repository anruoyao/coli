<?php

namespace Tests\Feature\Ads;

use App\Models\Ad;
use App\Models\Post;
use Tests\TestCase;
use App\Models\Media;
use App\Models\User;
use App\Enums\Ad\AdStatus;
use App\Enums\Ad\AdApproval;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Enums\Post\PostStatus;
use App\Enums\User\UserStatus;
use App\Settings\GuestSettings;
use App\Actions\Ad\DeleteAdAction;
use App\Services\Ad\AdPostSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Marketing\Concerns\CreatesUsers;

/**
 * 原生广告（影子帖）核心链路测试。
 *
 * 覆盖：生命周期同步（发布/暂停/完成/驳回/删除）、feed 槽位注入与轮换、
 * Resource 字段（is_ad/ad.cta/媒体来自广告）、onset 拉新不注入、
 * Policy 拦截直接改删、Explore/Profile 排除、访客流注入。
 *
 * 注：DatabaseTransactions（事务回滚）避免全量重建库超时；AppKey 门槛关闭。
 */
class NativeAdTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private const APP_KEY = 'clbPK-8f3k2m9xq4w7v1t6a5s0d2n8h4j6y1c';

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.app_key.enabled' => false]);

        // 槽位固定为 [3, 9]，与 config/ads.php 默认一致，保证断言可预测
        config(['ads.feed.enabled' => true, 'ads.feed.slots' => [3, 9]]);
    }

    // ===================== 构造工具 =====================

    private function makeAd(User $advertiser, array $overrides = []): Ad
    {
        $ad = Ad::create(array_merge([
            'user_id' => $advertiser->id,
            'title' => '原生广告测试标题',
            'content' => '这是一条用于测试的原生广告正文内容，长度满足校验要求。',
            'cta_text' => '立即了解',
            'target_url' => 'https://example.com/offer',
            'status' => AdStatus::PUBLISHED->value,
            'approval' => AdApproval::APPROVED->value,
            'total_budget' => 100,
            'spent_budget' => 0,
            'price_per_view' => 0.01,
        ], $overrides));

        $ad->media()->create([
            'source_path' => 'ads/creatives/test-'.uniqid().'.png',
            'disk' => 'public',
            'type' => MediaType::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'extension' => 'png',
            'mime' => 'image/png',
            'size' => 1024,
            'metadata' => [],
        ]);

        return $ad;
    }

    private function makePost(User $user, array $overrides = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'content' => '普通帖子 '.uniqid(),
            'type' => 'text',
            'status' => PostStatus::ACTIVE->value,
        ], $overrides));
    }

    // ===================== 生命周期同步 =====================

    public function test_sync_creates_active_shadow_post_when_published_and_approved(): void
    {
        $advertiser = $this->makeUser();
        $ad = $this->makeAd($advertiser);

        app(AdPostSyncService::class)->sync($ad);

        $post = $ad->post()->first();

        $this->assertNotEmpty($post);
        $this->assertEquals(PostStatus::ACTIVE, $post->status);
        $this->assertTrue($post->type->isImage());
        $this->assertEquals($advertiser->id, $post->user_id);
        // 正文 = 加粗标题 + 广告文案
        $this->assertStringContainsString($ad->title, $post->content);
        $this->assertStringContainsString($ad->content, $post->content);
        // 影子帖自身不建 media 行
        $this->assertCount(0, $post->media);
        // 不计入发帖数
        $this->assertEquals(0, $advertiser->refresh()->publications_count);
    }

    public function test_sync_is_idempotent(): void
    {
        $advertiser = $this->makeUser();
        $ad = $this->makeAd($advertiser);

        app(AdPostSyncService::class)->sync($ad);
        app(AdPostSyncService::class)->sync($ad);

        $this->assertEquals(1, $ad->post()->count());
    }

    public function test_sync_hides_shadow_post_on_pause_and_restores_on_republish(): void
    {
        $advertiser = $this->makeUser();
        $ad = $this->makeAd($advertiser);

        app(AdPostSyncService::class)->sync($ad);
        $postId = $ad->post()->first()->id;

        $ad->update(['status' => AdStatus::PAUSED]);
        app(AdPostSyncService::class)->sync($ad);

        $this->assertEquals(PostStatus::DRAFT, $ad->post()->first()->status);

        $ad->update(['status' => AdStatus::PUBLISHED]);
        app(AdPostSyncService::class)->sync($ad);

        $this->assertEquals(PostStatus::ACTIVE, Post::find($postId)->status);
    }

    public function test_sync_soft_deletes_shadow_post_on_completed_or_rejected(): void
    {
        $advertiser = $this->makeUser();

        // 预算耗尽完成
        $completedAd = $this->makeAd($advertiser, ['spent_budget' => 100, 'total_budget' => 100]);
        app(AdPostSyncService::class)->sync($completedAd);
        $completedAd->update(['status' => AdStatus::COMPLETED]);
        app(AdPostSyncService::class)->sync($completedAd);
        $this->assertEquals(PostStatus::DELETED, $completedAd->post()->first()->status);

        // 管理员驳回
        $rejectedAd = $this->makeAd($advertiser);
        app(AdPostSyncService::class)->sync($rejectedAd);
        $rejectedAd->update(['approval' => AdApproval::REJECTED]);
        app(AdPostSyncService::class)->sync($rejectedAd);
        $this->assertEquals(PostStatus::DELETED, $rejectedAd->post()->first()->status);
    }

    public function test_delete_ad_action_removes_shadow_post(): void
    {
        $advertiser = $this->makeUser();
        $ad = $this->makeAd($advertiser);

        app(AdPostSyncService::class)->sync($ad);
        $postId = $ad->post()->first()->id;

        (new DeleteAdAction($ad))->execute();

        $this->assertDatabaseMissing('posts', ['id' => $postId]);
        $this->assertDatabaseMissing('ads', ['id' => $ad->id]);
    }

    // ===================== Feed 注入 =====================

    public function test_feed_injects_ad_post_at_slot_with_cta_and_media(): void
    {
        $viewer = $this->makeUser();
        $advertiser = $this->makeUser();

        // 显式递增 created_at，避免同秒创建导致排序不稳定
        for ($i = 0; $i < 6; $i++) {
            $this->makePost($viewer, [
                'content' => 'POST_'.$i,
                'created_at' => now()->subMinutes(10 - $i),
            ]);
        }

        $ad = $this->makeAd($advertiser);
        app(AdPostSyncService::class)->sync($ad);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/timeline/feed')->assertOk();

        $items = $response->json('data');

        $this->assertCount(7, $items);

        // 槽位 [3]：第 3 条帖子之后（0 基索引 3）
        $adItem = $items[3];
        $this->assertTrue($adItem['is_ad']);
        $this->assertEquals('立即了解', $adItem['ad']['cta_text']);
        $this->assertEquals('https://example.com/offer', $adItem['ad']['target_url']);
        // 媒体来自广告素材
        $this->assertNotEmpty($adItem['relations']['media']);
        $this->assertStringContainsString('ads/creatives/', $adItem['relations']['media'][0]['source_url']);

        // 其余为普通帖子且无广告标记
        $this->assertFalse($items[0]['is_ad']);
        $this->assertEquals('POST_5', $items[0]['content']);
    }

    public function test_feed_rotates_ads_by_last_show_at(): void
    {
        $viewer = $this->makeUser();
        $advertiser = $this->makeUser();

        // 槽位 [3]：至少 3 条帖子广告才会注入
        for ($i = 0; $i < 4; $i++) {
            $this->makePost($viewer);
        }

        // A：刚展示过（冷却中）；B：从未展示
        $freshAd = $this->makeAd($advertiser, ['last_show_at' => now()]);
        $staleAd = $this->makeAd($advertiser);

        app(AdPostSyncService::class)->sync($freshAd);
        app(AdPostSyncService::class)->sync($staleAd);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/timeline/feed')->assertOk();

        $adItems = array_values(array_filter($response->json('data'), fn ($item) => $item['is_ad']));

        // 每页上限内只选中冷却外的广告
        $this->assertCount(1, $adItems);
        $this->assertStringContainsString($staleAd->title, $adItems[0]['content']);
        $this->assertStringNotContainsString($freshAd->title, $adItems[0]['content']);
    }

    public function test_feed_skips_injection_for_onset_requests(): void
    {
        $viewer = $this->makeUser();
        $advertiser = $this->makeUser();

        $firstPost = $this->makePost($viewer);
        $this->makePost($viewer, ['content' => 'LATER_POST']);

        $ad = $this->makeAd($advertiser);
        app(AdPostSyncService::class)->sync($ad);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/timeline/feed?filter[onset]='.$firstPost->id)
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertFalse($response->json('data.0.is_ad'));
    }

    public function test_guest_feed_injects_ad_post(): void
    {
        $author = $this->makeUser();
        $advertiser = $this->makeUser();

        for ($i = 0; $i < 4; $i++) {
            $this->makePost($author);
        }

        $ad = $this->makeAd($advertiser);
        app(AdPostSyncService::class)->sync($ad);

        $settings = app(GuestSettings::class);
        $settings->enabled = true;
        $settings->save();

        $response = $this->withHeader('X-App-Key', self::APP_KEY)
            ->getJson('/api/guest/v1/feed')
            ->assertOk();

        $adItems = array_values(array_filter($response->json('data'), fn ($item) => $item['is_ad']));

        $this->assertCount(1, $adItems);
        $this->assertEquals('立即了解', $adItems[0]['ad']['cta_text']);
        $this->assertNotEmpty($adItems[0]['relations']['media']);
        // 访客无互动权限
        $this->assertFalse($adItems[0]['meta']['permissions']['can_like']);
    }

    // ===================== 防护与排除 =====================

    public function test_ad_post_policy_blocks_update_and_delete(): void
    {
        $advertiser = $this->makeUser();
        $ad = $this->makeAd($advertiser);

        app(AdPostSyncService::class)->sync($ad);
        $post = $ad->post()->first();

        // 即使是影子帖作者（广告主本人）也不能直接改删
        $this->assertFalse($advertiser->can('update', $post));
        $this->assertFalse($advertiser->can('delete', $post));
    }

    public function test_explore_and_profile_exclude_ad_posts(): void
    {
        $viewer = $this->makeUser();

        // Explore 推荐流按 author() 过滤（type=AUTHOR），广告主需为 AUTHOR 才有基础帖可见
        $advertiser = $this->makeUser(['type' => \App\Enums\User\UserType::AUTHOR->value]);

        $this->makePost($advertiser, ['content' => 'NORMAL_POST']);
        $ad = $this->makeAd($advertiser);
        app(AdPostSyncService::class)->sync($ad);

        // Explore 推荐流（已注入广告位）：基础列表不含广告帖本体重复出现
        $exploreResponse = $this->actingAs($viewer, 'sanctum')->postJson('/api/explore/posts', [
            'filter' => ['page' => 1],
        ])->assertOk();

        $adPostCount = count(array_filter($exploreResponse->json('data'), fn ($item) => $item['is_ad']));
        $adOccurrences = count(array_filter(
            $exploreResponse->json('data'),
            fn ($item) => str_contains($item['content'], $ad->title)
        ));
        // 最多通过注入出现一次（不能作为普通帖子重复出现）
        $this->assertLessThanOrEqual(1, $adOccurrences);
        $this->assertEquals($adPostCount, $adOccurrences);

        // 广告主个人主页：完全不出现
        $profileResponse = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/profile/posts?id='.$advertiser->id.'&filter[cursor]=0&filter[type]=posts')
            ->assertOk();

        $contents = array_column($profileResponse->json('data'), 'content');
        $this->assertContains('NORMAL_POST', $contents);
        $this->assertEmpty(array_filter($contents, fn ($content) => str_contains($content, $ad->title)));
    }
}
