<?php

namespace Tests\Feature\Api\Auth;

use App\Actions\User\CreateUserAction;
use App\Enums\User\UserStatus;
use App\Mail\User\Settings\ConfirmationCodeMail;
use App\Models\EmailConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * App 注册邮箱验证码（Flutter Chatter 对接）端到端测试。
 *
 * 覆盖后台「注册邮箱验证」开关开启 / 关闭两种状态：
 * - 开启：必须先发码、携带正确 email_code 才能注册；错误码 / 过期 / 尝试次数受限；重发有冷却
 * - 关闭：注册无需验证码；发码接口直接 403
 */
class RegistrationEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const APP_KEY = 'clbPK-8f3k2m9xq4w7v1t6a5s0d2n8h4j6y1c';

    private string $email = 'newcomer@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // 显式开启注册 + 邮箱验证（默认场景），关闭场景的用例内部再覆盖
        config([
            'features.registration.enabled' => true,
            'features.reg_verification.enabled' => true,
            'security.auth.verification_code_resend_cooldown' => 60,
            'security.auth.verification_code_max_attempts' => 5,
        ]);
    }

    private function headers(): array
    {
        return [
            'X-App-Key' => self::APP_KEY,
            'X-App-Version' => '999.0.0',
            'X-App-Platform' => 'android',
        ];
    }

    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New',
            'username' => 'newcomer',
            'email' => $this->email,
            'password' => 'secret-password-123',
            'device_name' => 'phpunit',
        ], $overrides);
    }

    private function issueCode(): string
    {
        $this->postJson('/api/auth/email-code/send', ['email' => $this->email], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.email', $this->email)
            ->assertJsonPath('data.code_expires_seconds', 600);

        return EmailConfirmation::where('email', $this->email)->value('code');
    }

    public function test_config_endpoint_reports_verification_enabled(): void
    {
        $this->getJson('/api/auth/config', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.registration_enabled', true)
            ->assertJsonPath('data.email_verification_enabled', true)
            ->assertJsonPath('data.resend_cooldown_seconds', 60);
    }

    public function test_config_endpoint_reports_verification_disabled(): void
    {
        config(['features.reg_verification.enabled' => false]);

        $this->getJson('/api/auth/config', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.email_verification_enabled', false);
    }

    public function test_send_code_validates_email_and_rejects_duplicates(): void
    {
        // 非法邮箱
        $this->postJson('/api/auth/email-code/send', ['email' => 'not-an-email'], $this->headers())
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email']]);

        // 已注册邮箱
        (new CreateUserAction([
            'username' => 'taken',
            'first_name' => 'Taken',
            'email' => $this->email,
            'password' => 'secret-password-123',
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]))->execute();

        $this->postJson('/api/auth/email-code/send', ['email' => $this->email], $this->headers())
            ->assertStatus(422);

        Mail::assertNothingQueued();
    }

    public function test_send_code_creates_record_and_mails_code(): void
    {
        $this->postJson('/api/auth/email-code/send', ['email' => $this->email], $this->headers())
            ->assertOk();

        $record = EmailConfirmation::where('email', $this->email)->first();
        $this->assertNotNull($record);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $record->code);
        $this->assertNotNull($record->expires_at);
        $this->assertSame(0, (int) $record->attempts);

        Mail::assertQueued(ConfirmationCodeMail::class, 1);
    }

    public function test_resend_is_rate_limited_by_cooldown(): void
    {
        $this->issueCode();

        $this->postJson('/api/auth/email-code/resend', ['email' => $this->email], $this->headers())
            ->assertStatus(429)
            ->assertJsonPath('data.time_left', 60);

        // 冷却内未产生第二条邮件
        Mail::assertQueued(ConfirmationCodeMail::class, 1);
    }

    public function test_send_code_forbidden_when_verification_disabled(): void
    {
        config(['features.reg_verification.enabled' => false]);

        $this->postJson('/api/auth/email-code/send', ['email' => $this->email], $this->headers())
            ->assertStatus(403);
    }

    public function test_send_code_forbidden_when_registration_disabled(): void
    {
        config(['features.registration.enabled' => false]);

        $this->postJson('/api/auth/email-code/send', ['email' => $this->email], $this->headers())
            ->assertStatus(403);
    }

    public function test_register_requires_code_when_verification_enabled(): void
    {
        // 无验证码
        $this->postJson('/api/auth/register', $this->registerPayload(), $this->headers())
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email_code']]);

        $this->assertDatabaseMissing('users', ['email' => $this->email]);
    }

    public function test_register_rejects_wrong_code(): void
    {
        $this->issueCode();

        $this->postJson('/api/auth/register', $this->registerPayload(['email_code' => '000000']), $this->headers())
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['email_code']]);

        $this->assertSame(1, (int) EmailConfirmation::where('email', $this->email)->value('attempts'));
        $this->assertDatabaseMissing('users', ['email' => $this->email]);
    }

    public function test_register_locks_out_after_max_attempts(): void
    {
        $this->issueCode();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register', $this->registerPayload(['email_code' => '000000']), $this->headers())
                ->assertStatus(422);
        }

        // 尝试次数耗尽：验证码记录被作废，必须重新获取
        $this->assertDatabaseMissing('email_confirmations', ['email' => $this->email]);
        $this->assertDatabaseMissing('users', ['email' => $this->email]);
    }

    public function test_register_succeeds_with_correct_code_and_consumes_it(): void
    {
        $code = $this->issueCode();

        $this->postJson('/api/auth/register', $this->registerPayload(['email_code' => $code]), $this->headers())
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['token', 'user' => ['email', 'username']]]);

        // 验证码一次性消费
        $this->assertDatabaseMissing('email_confirmations', ['email' => $this->email]);
        $this->assertDatabaseHas('users', ['email' => $this->email]);
    }

    public function test_register_without_code_when_verification_disabled(): void
    {
        config(['features.reg_verification.enabled' => false]);

        $this->postJson('/api/auth/register', $this->registerPayload(), $this->headers())
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', ['email' => $this->email]);
    }
}
