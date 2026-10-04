<?php

namespace Tests\Feature\Feedback;

use App\Models\Report;
use Tests\TestCase;
use App\Models\Censor;
use App\Enums\CensorLevel;
use App\Models\ReportEmailLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use App\Mail\Admin\ReportNotificationMail;
use App\Models\ReportNotificationEmail;
use App\Settings\ReportNotificationSettings;
use App\Jobs\Feedback\SendReportNotificationEmailJob;
use App\Livewire\Admin\Config\ReportNotifications;
use App\Models\User;
use App\Enums\User\UserStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Feature\Marketing\Concerns\CreatesUsers;

/**
 * 举报邮件通知端到端测试。
 *
 * 覆盖：API 提交（comment 落库 / IP 记录 / Job 派发）、限流 429、敏感词 422、
 * 邮箱加密存储、Job 多邮箱发送 / 失败重试 / 已发送跳过 / 总开关、后台配置 CRUD 与审计日志。
 *
 * 注：使用 DatabaseTransactions（结构预建后仅事务回滚）而非 RefreshDatabase，
 *     避免 Laravel 11 逐方法全量重建库导致 MySQL 上超时。
 */
class ReportNotificationEmailTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // API 组最前挂 VerifyAppKey（X-App-Key）中间件，未携带密钥返回 404 伪装。
        // 业务测试聚焦举报链路本身，此处关闭密钥门槛（密钥校验属另一中间件职责）。
        config(['security.app_key.enabled' => false]);

        Mail::fake();
        Queue::fake();

        Cache::forget('censor_banned_words');
    }

    // ===================== API 提交 =====================

    /** 被举报目标需为 ACTIVE 状态（ReportController 经 User::activeById 查询）。 */
    private function activeUser(array $overrides = []): User
    {
        return $this->makeUser(array_merge(['status' => UserStatus::ACTIVE], $overrides));
    }

    public function test_submission_persists_report_and_dispatches_job(): void
    {
        $user = $this->makeUser();
        $target = $this->activeUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/feedback/report/send', [
            'type' => 'user',
            'reason_index' => 0,
            'reportable_id' => $target->id,
            'comment' => '<script>alert(1)</script> 这是补充说明',
        ]);

        $response->assertStatus(200);

        $report = Report::query()->where('reporter_id', $user->id)->first();

        $this->assertNotNull($report);
        // strip_tags 剥离标签但保留 script 内容为纯文本（无标签即无 XSS 执行面）
        $this->assertStringNotContainsString('<script', $report->reporter_comment);
        $this->assertStringContainsString('这是补充说明', $report->reporter_comment);
        $this->assertSame('127.0.0.1', $report->ip_address);

        Queue::assertPushed(SendReportNotificationEmailJob::class, fn ($job) => $job->reportId === $report->id);
    }

    public function test_eleventh_submission_is_rate_limited_with_retry_data(): void
    {
        $user = $this->makeUser();

        // 限流按 24h 窗口累计：对同一目标重复举报会先删后建不虚增计数，
        // 故需举报 10 个不同目标才触发账号维度限流。
        for($i = 0; $i < 10; $i++) {
            $target = $this->activeUser();

            $this->actingAs($user, 'sanctum')->postJson('/api/feedback/report/send', [
                'type' => 'user',
                'reason_index' => 0,
                'reportable_id' => $target->id,
            ])->assertStatus(200);
        }

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/feedback/report/send', [
            'type' => 'user',
            'reason_index' => 0,
            'reportable_id' => $this->activeUser()->id,
        ]);

        $response->assertStatus(429);
        $this->assertNotEmpty($response->json('message'));
        $this->assertGreaterThan(0, $response->json('data.retry_after'));
        $this->assertSame(10, $response->json('data.limit'));
        $this->assertSame(24, $response->json('data.window_hours'));
        $this->assertNotNull($response->json('data.next_available_at'));
        $response->assertHeader('Retry-After');
    }

    public function test_comment_with_banned_word_is_rejected_with_422(): void
    {
        Censor::create(['word' => 'badword', 'level' => CensorLevel::BANNED]);

        $user = $this->makeUser();
        $target = $this->activeUser();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/feedback/report/send', [
            'type' => 'user',
            'reason_index' => 0,
            'reportable_id' => $target->id,
            'comment' => '包含 badword 的说明',
        ]);

        $response->assertStatus(422);
        $this->assertNotNull($response->json('errors.comment'));
        $this->assertDatabaseMissing('reports', ['reporter_id' => $user->id]);
    }

    // ===================== 邮箱加密存储 =====================

    public function test_admin_emails_are_encrypted_at_rest(): void
    {
        $email = ReportNotificationEmail::create([
            'email' => 'secret@example.com',
            'enabled' => true,
        ]);

        $rawValue = DB::table('report_notification_emails')->where('id', $email->id)->value('email');

        $this->assertNotSame('secret@example.com', $rawValue); // 库中为密文
        $this->assertSame('secret@example.com', $email->fresh()->email); // 模型读回明文
    }

    // ===================== Job 发送 =====================

    private function makeReport(): Report
    {
        $reporter = $this->makeUser();
        $target = $this->activeUser();

        return $target->reports()->create([
            'reporter_id' => $reporter->id,
            'reason_index' => 0,
            'type' => \App\Enums\Report\ReportType::from('user'),
            'reporter_comment' => '测试举报',
            'ip_address' => '1.2.3.4',
        ]);
    }

    public function test_job_sends_to_all_enabled_recipients_and_writes_logs(): void
    {
        ReportNotificationEmail::create(['email' => 'first@example.com', 'enabled' => true]);
        ReportNotificationEmail::create(['email' => 'second@example.com', 'enabled' => true]);
        ReportNotificationEmail::create(['email' => 'disabled@example.com', 'enabled' => false]);

        $report = $this->makeReport();

        (new SendReportNotificationEmailJob($report->id))->handle();

        Mail::assertSent(ReportNotificationMail::class, 2);
        Mail::assertSent(ReportNotificationMail::class, fn ($mail) => $mail->hasTo('first@example.com'));
        Mail::assertSent(ReportNotificationMail::class, fn ($mail) => $mail->hasTo('second@example.com'));
        Mail::assertNotSent(ReportNotificationMail::class, fn ($mail) => $mail->hasTo('disabled@example.com'));

        $this->assertSame(2, ReportEmailLog::query()->where('status', ReportEmailLog::STATUS_SENT)->count());
        $this->assertSame(2, ReportEmailLog::query()->where('report_id', $report->id)->count());
    }

    public function test_job_skips_already_sent_recipients_on_retry(): void
    {
        $report = $this->makeReport();

        ReportNotificationEmail::create(['email' => 'first@example.com', 'enabled' => true]);
        ReportNotificationEmail::create(['email' => 'second@example.com', 'enabled' => true]);

        // 首个邮箱在上一轮已发送成功（幂等键为明文哈希，recipient_email 加密不可查）
        ReportEmailLog::create([
            'report_id' => $report->id,
            'recipient_email' => 'first@example.com',
            'recipient_hash' => sha1('first@example.com'),
            'status' => ReportEmailLog::STATUS_SENT,
            'attempts' => 1,
            'sent_at' => now(),
        ]);

        (new SendReportNotificationEmailJob($report->id))->handle();

        // 重试只补发未成功者
        Mail::assertSent(ReportNotificationMail::class, 1);
        Mail::assertSent(ReportNotificationMail::class, fn ($mail) => $mail->hasTo('second@example.com'));
    }

    public function test_job_marks_failed_and_throws_for_queue_retry(): void
    {
        $report = $this->makeReport();

        ReportNotificationEmail::create(['email' => 'broken@example.com', 'enabled' => true]);

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \Exception('smtp down'));

        $this->expectException(\Exception::class);

        try {
            (new SendReportNotificationEmailJob($report->id))->handle();
        } finally {
            $log = ReportEmailLog::query()->where('report_id', $report->id)->first();

            $this->assertNotNull($log);
            $this->assertSame(ReportEmailLog::STATUS_FAILED, $log->status);
            $this->assertSame(1, $log->attempts);
            $this->assertSame('smtp down', $log->error);
        }
    }

    public function test_job_respects_feature_toggle_at_send_time(): void
    {
        $settings = app(ReportNotificationSettings::class);
        $settings->enabled = false;
        $settings->save();

        ReportNotificationEmail::create(['email' => 'first@example.com', 'enabled' => true]);

        $report = $this->makeReport();

        (new SendReportNotificationEmailJob($report->id))->handle();

        Mail::assertNothingSent();
    }

    // ===================== 后台配置（Livewire） =====================

    public function test_admin_can_add_edit_toggle_and_remove_emails_with_audit_log(): void
    {
        Livewire::test(ReportNotifications::class)
            ->set('newEmail', 'admin@example.com')
            ->call('addEmail')
            ->assertHasNoErrors();

        $stored = ReportNotificationEmail::first();

        $this->assertNotNull($stored);
        $this->assertSame('admin@example.com', $stored->email);

        // 格式校验
        Livewire::test(ReportNotifications::class)
            ->set('newEmail', 'not-an-email')
            ->call('addEmail')
            ->assertHasErrors(['newEmail']);

        // 重复校验
        Livewire::test(ReportNotifications::class)
            ->set('newEmail', 'admin@example.com')
            ->call('addEmail')
            ->assertHasErrors(['newEmail']);

        // 单邮箱开关
        Livewire::test(ReportNotifications::class)
            ->call('toggleEmail', $stored->id);

        $this->assertFalse($stored->fresh()->enabled);

        // 行内编辑
        Livewire::test(ReportNotifications::class)
            ->call('startEditing', $stored->id)
            ->set('editingEmail', 'updated@example.com')
            ->call('updateEmail')
            ->assertHasNoErrors();

        $this->assertSame('updated@example.com', $stored->fresh()->email);

        // 删除
        Livewire::test(ReportNotifications::class)
            ->call('removeEmail', $stored->id);

        $this->assertSame(0, ReportNotificationEmail::count());

        // 配置修改日志
        $actions = \App\Models\AdminConfigChangeLog::query()
            ->where('config_key', 'report_notification')
            ->pluck('action')
            ->toArray();

        $this->assertContains('email_created', $actions);
        $this->assertContains('email_updated', $actions);
        $this->assertContains('email_deleted', $actions);
    }

    public function test_feature_toggle_persists_setting_and_logs(): void
    {
        Livewire::test(ReportNotifications::class)
            ->set('featureEnabled', false)
            ->call('toggleFeature');

        $this->assertFalse(app(ReportNotificationSettings::class)->enabled);

        $this->assertDatabaseHas('admin_config_change_logs', [
            'config_key' => 'report_notification',
            'action' => 'feature_toggled',
        ]);
    }
}
