<?php

namespace Tests\Feature\Marketing;

use App\Enums\NotificationType;
use Tests\TestCase;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use App\Jobs\Marketing\SendMarketingInAppNotificationJob;

class SendMarketingInAppNotificationJobTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.marketing.enabled' => true,
            'notifications.marketing.in_app_enabled' => true,
            // 测试环境无 Reverb/广播驱动，禁用广播通道以确定性地走 database 通道
            'notifications.broadcast.enabled' => false,
        ]);
    }

    private function campaign(array $overrides = []): MarketingCampaign
    {
        return MarketingCampaign::create(array_merge([
            'title' => 'In-app Campaign',
            'subject' => '',
            'content' => 'Platform message body',
            'email_enabled' => false,
            'in_app_enabled' => true,
            'target_type' => MarketingCampaign::TARGET_ALL,
            'status' => MarketingCampaign::STATUS_SENDING,
            'created_by' => 1,
        ], $overrides));
    }

    private function recipient(MarketingCampaign $campaign, int $userId): MarketingCampaignRecipient
    {
        return MarketingCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'user_id' => $userId,
            'email' => null,
            'email_status' => MarketingCampaignRecipient::EMAIL_SKIPPED,
            'in_app_status' => MarketingCampaignRecipient::INAP_QUEUED,
        ]);
    }

    public function test_creates_in_app_notification_for_opted_in_user(): void
    {
        $user = $this->makeUser();
        $this->attachNotificationSettings($user, NotificationType::PUSH, ['platform_notifications' => true]);

        $campaign = $this->campaign();
        $recipient = $this->recipient($campaign, $user->id);

        (new SendMarketingInAppNotificationJob($campaign->id, $recipient->id))->handle();

        $this->assertSame(MarketingCampaignRecipient::INAP_SENT, $recipient->fresh()->in_app_status);
        $this->assertSame(1, $campaign->fresh()->in_app_sent_count);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => 'marketing.platform',
        ]);

        $notification = $user->notifications()->first();
        $data = $notification->data;

        $this->assertSame('marketing', $data['message_group']);
        $this->assertSame('In-app Campaign', $data['entity']['title']);
        $this->assertSame('Platform message body', $data['entity']['content']);
        $this->assertTrue($data['metadata']['marketing']);
        $this->assertSame('system', $data['actor']['type']);
    }

    public function test_skips_opted_out_user_without_creating_notification(): void
    {
        $user = $this->makeUser();
        $this->attachNotificationSettings($user, NotificationType::PUSH, ['platform_notifications' => false]);

        $campaign = $this->campaign();
        $recipient = $this->recipient($campaign, $user->id);

        (new SendMarketingInAppNotificationJob($campaign->id, $recipient->id))->handle();

        $this->assertSame(MarketingCampaignRecipient::INAP_SKIPPED, $recipient->fresh()->in_app_status);
        $this->assertSame('opt_out', $recipient->fresh()->in_app_error);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_user_without_push_settings_is_default_opted_in(): void
    {
        $user = $this->makeUser(); // 无任何通知设置记录 → 按「默认开启」处理

        $campaign = $this->campaign();
        $recipient = $this->recipient($campaign, $user->id);

        (new SendMarketingInAppNotificationJob($campaign->id, $recipient->id))->handle();

        $this->assertSame(MarketingCampaignRecipient::INAP_SENT, $recipient->fresh()->in_app_status);
        $this->assertDatabaseCount('notifications', 1);
    }
}