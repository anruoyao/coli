<?php

namespace Tests\Feature\Marketing;

use App\Models\Post;
use App\Models\Media;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use App\Models\MarketingCampaign;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Enums\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use App\Models\MarketingCampaignRecipient;
use App\Mail\MarketingNotificationMail;
use App\Jobs\Marketing\SendMarketingInAppNotificationJob;
use App\Jobs\Marketing\SendMarketingEmailJob;
use App\Services\Marketing\CampaignService;
use App\Http\Controllers\Admin\Marketing\CampaignController;

/**
 * 富媒体营销通知集成测试：
 *  - 帖子快照构建（顺序、过滤、封面/摘要/作者）；
 *  - 邮件携带 Banner/标题样式/帖子卡片并渲染进 HTML；
 *  - 站内通知 data 携带富媒体字段；
 *  - dispatchTick 每批构建一次快照随 Job 传递；
 *  - 控制器 post_ids 解析（数字 ID / 帖子 URL / 非法行过滤）。
 */
class RichContentTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.marketing.enabled' => true,
            'notifications.marketing.email.enabled' => true,
            'notifications.marketing.in_app_enabled' => true,
            'notifications.marketing.broadcast.enabled' => false,
            'notifications.broadcast.enabled' => false,
        ]);

        app(\App\Services\Marketing\EmailRateLimiter::class)->reset();
    }

    private function campaign(array $overrides = []): MarketingCampaign
    {
        return MarketingCampaign::create(array_merge([
            'title' => 'Rich Campaign',
            'subject' => 'Rich Subject',
            'content' => 'Rich body',
            'email_enabled' => true,
            'in_app_enabled' => true,
            'target_type' => MarketingCampaign::TARGET_ALL,
            'status' => MarketingCampaign::STATUS_SENDING,
            'created_by' => 1,
        ], $overrides));
    }

    private function makePost(int $userId, array $overrides = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $userId,
            'content' => str_repeat('A very interesting community post. ', 10),
            'status' => 'active',
            'type' => 'text',
            'text_language' => 'en',
        ], $overrides));
    }

    private function attachCover(Post $post): Media
    {
        return Media::create([
            'mediaable_id' => $post->id,
            'mediaable_type' => Post::class,
            'source_path' => "posts/{$post->id}/cover.jpg",
            'thumbnail_path' => "posts/{$post->id}/cover_thumb.jpg",
            'type' => MediaType::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'disk' => 'public',
            'thumbnail_disk' => 'public',
            // MediaCreatedEvent 监听器（存储统计）要求字节数字段，缺失会触发类型错误
            'size' => '1024',
            'thumbnail_size' => '',
        ]);
    }

    public function test_build_post_snapshots_filters_inactive_and_keeps_order(): void
    {
        $author = $this->makeUser();

        $postA = $this->makePost($author->id);
        $postB = $this->makePost($author->id);
        $this->makePost($author->id, ['status' => 'deleted']); // 应被过滤
        $this->makePost($author->id, ['status' => 'draft']);   // 应被过滤

        $this->attachCover($postA);

        $service = app(CampaignService::class);

        // 输入顺序 B → A（乱序），快照按输入顺序返回
        $snapshots = $service->buildPostSnapshots([$postB->id, $postA->id, 999999]);

        $this->assertCount(2, $snapshots);
        $this->assertSame($postB->id, $snapshots[0]['id']);
        $this->assertSame($postA->id, $snapshots[1]['id']);

        // 基础字段
        $this->assertSame($postA->hashid, $snapshots[1]['hash_id']);
        $this->assertSame(url("publication/{$postA->hashid}"), $snapshots[1]['url']);
        $this->assertSame('Test User', $snapshots[1]['author_name']);
        $this->assertSame(0, $snapshots[1]['reactions_count']);
        $this->assertSame(0, $snapshots[1]['comments_count']);
        $this->assertArrayHasKey('excerpt', $snapshots[1]);

        // 摘要限长 80 + 省略号
        $this->assertSame(80 + strlen('…'), strlen($snapshots[1]['excerpt']));

        // 封面：A 有图 → thumbnail_url；B 无图 → null
        $this->assertNotNull($snapshots[1]['cover_url']);
        $this->assertStringContainsString("posts/{$postA->id}/cover_thumb.jpg", $snapshots[1]['cover_url']);
        $this->assertNull($snapshots[0]['cover_url']);
    }

    public function test_email_carries_rich_fields_and_renders_into_html(): void
    {
        $user = $this->makeUser(['language' => 'en']);
        $this->attachNotificationSettings($user, NotificationType::EMAIL, ['platform_notifications' => true]);

        $author = $this->makeUser();
        $post = $this->makePost($author->id);
        $this->attachCover($post);

        $campaign = $this->campaign([
            'image_url' => 'https://cdn.example.com/banner.jpg',
            'title_size' => 'lg',
            'title_weight' => 'bold',
            'post_ids' => [$post->id],
        ]);

        $recipient = MarketingCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'email_status' => MarketingCampaignRecipient::EMAIL_QUEUED,
            'in_app_status' => MarketingCampaignRecipient::INAP_SKIPPED,
        ]);

        Mail::fake();

        (new SendMarketingEmailJob($campaign->id, $recipient->id))->handle();

        Mail::assertSent(MarketingNotificationMail::class, function (MarketingNotificationMail $mail) use ($user, $post) {
            $this->assertTrue($mail->hasTo($user->email));
            $this->assertSame('https://cdn.example.com/banner.jpg', $mail->imageUrl);
            $this->assertSame(['title_size' => 'lg', 'title_weight' => 'bold'], $mail->style);
            $this->assertCount(1, $mail->posts);
            $this->assertSame($post->hashid, $mail->posts[0]['hash_id']);

            // 渲染 HTML：Banner、标题字号/字重、帖子卡片（摘要 + 阅读全文链接）均在邮件正文中
            $html = $mail->render();

            $this->assertStringContainsString('https://cdn.example.com/banner.jpg', $html);
            $this->assertStringContainsString('font-size: 22px', $html);
            $this->assertStringContainsString('font-weight: 700', $html);
            $this->assertStringContainsString($post->hashid, $html);
            $this->assertStringContainsString('Read full post', $html);

            return true;
        });

        $this->assertSame(MarketingCampaignRecipient::EMAIL_SENT, $recipient->fresh()->email_status);
    }

    public function test_email_locale_localizes_post_section_labels(): void
    {
        $user = $this->makeUser(['language' => 'zh']);
        $author = $this->makeUser();
        $post = $this->makePost($author->id);

        $campaign = $this->campaign(['post_ids' => [$post->id]]);

        $recipient = MarketingCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'email_status' => MarketingCampaignRecipient::EMAIL_QUEUED,
            'in_app_status' => MarketingCampaignRecipient::INAP_SKIPPED,
        ]);

        Mail::fake();

        (new SendMarketingEmailJob($campaign->id, $recipient->id))->handle();

        Mail::assertSent(MarketingNotificationMail::class, function (MarketingNotificationMail $mail) {
            $this->assertStringContainsString('站内帖子', $mail->render());
            $this->assertStringContainsString('阅读全文', $mail->render());

            return true;
        });
    }

    public function test_in_app_notification_data_carries_rich_fields(): void
    {
        $user = $this->makeUser();
        $this->attachNotificationSettings($user, NotificationType::PUSH, ['platform_notifications' => true]);

        $author = $this->makeUser();
        $post = $this->makePost($author->id);
        $this->attachCover($post);

        $campaign = $this->campaign([
            'image_url' => 'https://cdn.example.com/banner.jpg',
            'title_size' => 'lg',
            'title_weight' => 'bold',
            'post_ids' => [$post->id],
        ]);

        $recipient = MarketingCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => null,
            'email_status' => MarketingCampaignRecipient::EMAIL_SKIPPED,
            'in_app_status' => MarketingCampaignRecipient::INAP_QUEUED,
        ]);

        // Job 不传快照 → 走兜底路径：Job 内按 campaign.post_ids 现算
        (new SendMarketingInAppNotificationJob($campaign->id, $recipient->id))->handle();

        $data = $user->notifications()->first()->data;

        $this->assertSame('https://cdn.example.com/banner.jpg', $data['entity']['image_url']);
        $this->assertSame(['title_size' => 'lg', 'title_weight' => 'bold'], $data['entity']['style']);
        $this->assertSame('https://cdn.example.com/banner.jpg', $data['metadata']['image_url']);
        $this->assertSame('lg', $data['metadata']['style']['title_size']);
        $this->assertCount(1, $data['metadata']['posts']);
        $this->assertSame($post->hashid, $data['metadata']['posts'][0]['hash_id']);
        $this->assertNotNull($data['metadata']['posts'][0]['cover_url']);
    }

    public function test_dispatch_tick_passes_prebuilt_post_snapshots_to_jobs(): void
    {
        $user = $this->makeUser();
        $author = $this->makeUser();
        $post = $this->makePost($author->id);

        $campaign = $this->campaign(['post_ids' => [$post->id]]);

        MarketingCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'email' => $user->email,
            'email_status' => MarketingCampaignRecipient::EMAIL_PENDING,
            'in_app_status' => MarketingCampaignRecipient::INAP_PENDING,
        ]);

        Queue::fake();
        app(CampaignService::class)->dispatchTick($campaign->fresh());

        // 同批 Job 共享同一份预构建快照（每批仅查询一次，避免每收件人重复构建）
        Queue::assertPushed(SendMarketingEmailJob::class, fn (SendMarketingEmailJob $job) => count($job->posts) === 1);
        Queue::assertPushed(SendMarketingInAppNotificationJob::class, fn (SendMarketingInAppNotificationJob $job) => count($job->posts) === 1);
    }

    public function test_controller_parses_post_ids_from_numeric_urls_and_hashids(): void
    {
        $author = $this->makeUser();
        $postA = $this->makePost($author->id);
        $postB = $this->makePost($author->id);

        $controller = new CampaignController(app(CampaignService::class));
        $method = new \ReflectionMethod($controller, 'parsePostIds');

        $request = Request::create('/', 'POST', [
            'post_ids' => implode("\n", [
                (string) $postA->id,                                             // 数字 ID
                url("publication/{$postB->hashid}"),                             // 帖子 URL
                url("publication/{$postA->hashid}"),                             // 重复 → 去重
                'https://example.com/some/other/page',                           // 非帖子链接 → 丢弃
                'not-a-valid-entry',                                             // 非法 hashid → 丢弃
            ]),
        ]);

        $ids = $method->invoke($controller, $request);

        $this->assertSame([$postA->id, $postB->id], $ids);
    }

    public function test_controller_returns_null_for_empty_post_ids(): void
    {
        $controller = new CampaignController(app(CampaignService::class));
        $method = new \ReflectionMethod($controller, 'parsePostIds');

        $request = Request::create('/', 'POST', ['post_ids' => "  \n  "]);

        $this->assertNull($method->invoke($controller, $request));
    }
}
