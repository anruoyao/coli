<?php

namespace Tests\Feature\Marketing;

use App\Models\User;
use App\Enums\User\UserType;
use Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use App\Models\MarketingCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use App\Jobs\Marketing\SendMarketingInAppNotificationJob;
use App\Jobs\Marketing\SendMarketingEmailJob;
use App\Services\Marketing\CampaignService;

class CampaignServiceTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.marketing.enabled' => true,
            'notifications.marketing.email.enabled' => true,
            'notifications.marketing.in_app_enabled' => true,
            'notifications.marketing.dispatch.per_tick' => 50,
            'notifications.marketing.dispatch.in_app_per_tick' => 200,
        ]);
    }

    private function campaign(array $overrides = []): MarketingCampaign
    {
        return MarketingCampaign::create(array_merge([
            'title' => 'Test Campaign',
            'subject' => 'Test Subject',
            'content' => "Hello from ColibriPlus marketing.\nSecond line.",
            'email_enabled' => true,
            'in_app_enabled' => true,
            'target_type' => MarketingCampaign::TARGET_ALL,
            'status' => MarketingCampaign::STATUS_DRAFT,
            'created_by' => 1,
        ], $overrides));
    }

    public function test_all_target_builds_recipients_for_all_users(): void
    {
        $this->makeUserWithSettings();
        $this->makeUserWithSettings();
        $this->makeUserWithSettings(false, false); // 双方都关闭的用户同样进入快照（发送时再跳过）

        $campaign = $this->campaign();

        $service = app(CampaignService::class);
        Queue::fake();
        $service->start($campaign);

        $this->assertSame(3, $campaign->recipients()->count());
        $this->assertSame(MarketingCampaign::STATUS_SENDING, $campaign->fresh()->status);
        $this->assertSame(3, $campaign->fresh()->email_recipient_count);
        $this->assertSame(3, $campaign->fresh()->in_app_recipient_count);

        Queue::assertPushed(SendMarketingEmailJob::class, 3);
        Queue::assertPushed(SendMarketingInAppNotificationJob::class, 3);
    }

    public function test_manual_target_resolves_usernames_and_ids(): void
    {
        $target = $this->makeUser();
        $other = $this->makeUser();
        $third = $this->makeUser();

        $campaign = $this->campaign([
            'target_type' => MarketingCampaign::TARGET_MANUAL,
            'target_user_ids' => [$target->username, (string) $third->id],
        ]);

        $service = app(CampaignService::class);
        Queue::fake();
        $service->start($campaign);

        $recipientIds = $campaign->recipients()->pluck('user_id')->sort()->values();

        $this->assertEqualsCanonicalizing([$target->id, $third->id], $recipientIds->all());
        $this->assertNotContains($other->id, $recipientIds->all());
    }

    public function test_manual_target_supports_raw_email_without_account(): void
    {
        $campaign = $this->campaign([
            'target_type' => MarketingCampaign::TARGET_MANUAL,
            'target_user_ids' => ['marketingtest@example.com'],
        ]);

        $service = app(CampaignService::class);
        Queue::fake();
        $service->start($campaign);

        $recipient = $campaign->recipients()->first();

        $this->assertNotNull($recipient);
        $this->assertNull($recipient->user_id);
        $this->assertSame('marketingtest@example.com', $recipient->email);
        // 原始邮箱收件人：仅走邮件通道，站内通道跳过
        $this->assertSame('pending', $recipient->email_status);
        $this->assertSame('skipped', $recipient->in_app_status);
        $this->assertSame(1, $campaign->fresh()->email_recipient_count);
        $this->assertSame(0, $campaign->fresh()->in_app_recipient_count);
    }

    public function test_manual_target_email_resolves_to_existing_user(): void
    {
        $user = $this->makeUser(); // email 形如 tester_xxx@example.com

        $campaign = $this->campaign([
            'target_type' => MarketingCampaign::TARGET_MANUAL,
            'target_user_ids' => [$user->email],
        ]);

        $service = app(CampaignService::class);
        Queue::fake();
        $service->start($campaign);

        $recipient = $campaign->recipients()->first();

        $this->assertSame($user->id, $recipient->user_id);
        $this->assertSame($user->email, $recipient->email);
    }

    public function test_type_target_filters_by_user_type(): void
    {
        $author = $this->makeUser(['type' => UserType::AUTHOR->value]);
        $this->makeUser(['type' => UserType::READER->value]);

        $campaign = $this->campaign([
            'target_type' => MarketingCampaign::TARGET_TYPE,
            'target_user_type' => UserType::AUTHOR->value,
        ]);

        $service = app(CampaignService::class);
        Queue::fake();
        $service->start($campaign);

        $this->assertSame([$author->id], $campaign->recipients()->pluck('user_id')->all());
    }

    public function test_email_channel_disabled_marks_recipients_skipped_and_does_not_dispatch(): void
    {
        $this->makeUser();
        $this->makeUser();

        $campaign = $this->campaign(['email_enabled' => false]);

        $service = app(CampaignService::class);
        Queue::fake();
        $service->start($campaign);

        $this->assertSame(0, $campaign->fresh()->email_recipient_count);
        $this->assertSame(0, $campaign->fresh()->email_sent_count);
        $this->assertSame(2, $campaign->recipients()->where('email_status', 'skipped')->count());

        Queue::assertNotPushed(SendMarketingEmailJob::class);
        Queue::assertPushed(SendMarketingInAppNotificationJob::class, 2);
    }

    public function test_finalize_marks_campaign_completed_when_all_processed(): void
    {
        $this->makeUser();

        $campaign = $this->campaign(['email_enabled' => false, 'in_app_enabled' => false]);

        $service = app(CampaignService::class);
        $service->start($campaign);

        // 两个通道均被初始标记为 skipped → dispatchTick 后即无待处理项
        $this->assertSame(MarketingCampaign::STATUS_COMPLETED, $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->finished_at);
    }

    public function test_cancel_stops_an_active_campaign(): void
    {
        $this->makeUser();

        $campaign = $this->campaign(['email_enabled' => false, 'in_app_enabled' => true]);

        $service = app(CampaignService::class);
        Queue::fake();
        $service->start($campaign);
        $service->cancel($campaign->fresh());

        $this->assertSame(MarketingCampaign::STATUS_CANCELLED, $campaign->fresh()->status);
    }
}