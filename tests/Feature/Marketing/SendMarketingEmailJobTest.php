<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use App\Models\MarketingCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use App\Models\MarketingCampaignRecipient;
use App\Mail\MarketingNotificationMail;
use App\Services\Marketing\EmailRateLimiter;
use App\Jobs\Marketing\SendMarketingEmailJob;

class SendMarketingEmailJobTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.marketing.enabled' => true,
            'notifications.marketing.email.enabled' => true,
            'notifications.marketing.email.initial_per_minute' => 100,
            'notifications.marketing.email.min_per_minute' => 10,
            'notifications.marketing.email.max_per_minute' => 200,
        ]);

        Mail::fake();
        Queue::fake();

        app(EmailRateLimiter::class)->reset();
    }

    private function campaign(array $overrides = []): MarketingCampaign
    {
        return MarketingCampaign::create(array_merge([
            'title' => 'Job Test Campaign',
            'subject' => 'Job Test Subject',
            'content' => 'Campaign body',
            'email_enabled' => true,
            'in_app_enabled' => false,
            'target_type' => MarketingCampaign::TARGET_ALL,
            'status' => MarketingCampaign::STATUS_SENDING,
            'created_by' => 1,
        ], $overrides));
    }

    private function recipient(MarketingCampaign $campaign, int $userId, string $email): MarketingCampaignRecipient
    {
        return MarketingCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'user_id' => $userId,
            'email' => $email,
            'email_status' => MarketingCampaignRecipient::EMAIL_QUEUED,
            'in_app_status' => MarketingCampaignRecipient::INAP_SKIPPED,
        ]);
    }

    public function test_sends_mail_to_opted_in_user_and_marks_sent(): void
    {
        $user = $this->makeUser(); // 默认邮箱 example.com，测试环境不会真正投递
        $this->attachNotificationSettings($user, \App\Enums\NotificationType::EMAIL, ['platform_notifications' => true]);

        $campaign = $this->campaign();
        $recipient = $this->recipient($campaign, $user->id, $user->email);

        (new SendMarketingEmailJob($campaign->id, $recipient->id))->handle();

        Mail::assertSent(MarketingNotificationMail::class, fn (MarketingNotificationMail $mail) => $mail->hasTo($user->email));

        $this->assertSame(MarketingCampaignRecipient::EMAIL_SENT, $recipient->fresh()->email_status);
        $this->assertSame(1, $campaign->fresh()->email_sent_count);
    }

    public function test_sends_to_raw_email_recipient_without_account(): void
    {
        $campaign = $this->campaign();

        // 无 user（user_id=null）的原始邮箱收件人：直接发往该地址，不校验任何用户开关
        $recipient = MarketingCampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'user_id' => null,
            'email' => 'marketingtest@example.com',
            'email_status' => MarketingCampaignRecipient::EMAIL_QUEUED,
            'in_app_status' => MarketingCampaignRecipient::INAP_SKIPPED,
        ]);

        (new SendMarketingEmailJob($campaign->id, $recipient->id))->handle();

        Mail::assertSent(MarketingNotificationMail::class, fn (MarketingNotificationMail $mail) => $mail->hasTo('marketingtest@example.com'));
        $this->assertSame(MarketingCampaignRecipient::EMAIL_SENT, $recipient->fresh()->email_status);
    }

    public function test_user_without_email_settings_row_is_default_opted_in(): void
    {
        $user = $this->makeUser(); // 无邮件设置行 → 按「默认开启」处理

        $campaign = $this->campaign();
        $recipient = $this->recipient($campaign, $user->id, $user->email);

        (new SendMarketingEmailJob($campaign->id, $recipient->id))->handle();

        Mail::assertSent(MarketingNotificationMail::class, fn (MarketingNotificationMail $mail) => $mail->hasTo($user->email));
        $this->assertSame(MarketingCampaignRecipient::EMAIL_SENT, $recipient->fresh()->email_status);
    }

    public function test_skips_opted_out_user_without_sending(): void
    {
        $user = $this->makeUser();
        $this->attachNotificationSettings($user, \App\Enums\NotificationType::EMAIL, ['platform_notifications' => false]);

        $campaign = $this->campaign();
        $recipient = $this->recipient($campaign, $user->id, $user->email);

        (new SendMarketingEmailJob($campaign->id, $recipient->id))->handle();

        Mail::assertNothingSent();
        $this->assertSame(MarketingCampaignRecipient::EMAIL_SKIPPED, $recipient->fresh()->email_status);
        $this->assertSame('opt_out', $recipient->fresh()->email_error);
    }

    public function test_releases_job_when_rate_limiter_has_no_budget(): void
    {
        $user = $this->makeUser();
        $this->attachNotificationSettings($user, \App\Enums\NotificationType::EMAIL);

        $campaign = $this->campaign();
        $recipient = $this->recipient($campaign, $user->id, $user->email);

        $limiter = new EmailRateLimiter();
        $limiter->reset();
        // 把配额耗尽：初始 100 封/分钟，占用全部 → 无剩余
        for ($i = 0; $i < 100; $i++) {
            $limiter->acquire();
        }

        // 替换容器中的限速器实例，让 Job 使用同一个实例
        $this->app->instance(EmailRateLimiter::class, $limiter);

        (new SendMarketingEmailJob($campaign->id, $recipient->id))->handle();

        Mail::assertNothingSent();
        // 无配额时保持 queued，等待调度/Job 释放后重试
        $this->assertSame(MarketingCampaignRecipient::EMAIL_QUEUED, $recipient->fresh()->email_status);
    }

    public function test_rejects_send_when_marketing_module_disabled(): void
    {
        $user = $this->makeUser();
        $this->attachNotificationSettings($user, \App\Enums\NotificationType::EMAIL);

        $campaign = $this->campaign();
        $recipient = $this->recipient($campaign, $user->id, $user->email);

        config(['notifications.marketing.email.enabled' => false]);

        (new SendMarketingEmailJob($campaign->id, $recipient->id))->handle();

        Mail::assertNothingSent();
        $this->assertSame(MarketingCampaignRecipient::EMAIL_SKIPPED, $recipient->fresh()->email_status);
    }
}