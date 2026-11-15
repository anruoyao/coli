<?php

namespace Tests\Feature\Security;

use App\Models\EmailConfirmation;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class PasswordResetLifecycleTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'security.app_key.enabled' => false,
            'security.auth.password_reset_expires_minutes' => 30,
        ]);
    }

    public function test_api_password_reset_consumes_token_and_revokes_existing_tokens(): void
    {
        $user = $this->makeUser(['password' => 'old-password']);
        $confirmation = app(PasswordResetService::class)->issue($user->email);
        $user->createToken('old-device');

        $this->postJson('/api/auth/reset-password', [
            'token' => $confirmation->token,
            'email' => $user->email,
            'password' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('email_confirmations', ['token' => $confirmation->token]);

        $this->postJson('/api/auth/reset-password', [
            'token' => $confirmation->token,
            'email' => $user->email,
            'password' => 'another-password-123',
        ])->assertNotFound();
    }

    public function test_expired_tokens_are_rejected_and_registration_codes_are_not_removed_when_issuing(): void
    {
        $user = $this->makeUser();
        $code = EmailConfirmation::create([
            'email' => $user->email,
            'token' => (string) Str::uuid(),
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $confirmation = app(PasswordResetService::class)->issue($user->email);
        $this->assertDatabaseHas('email_confirmations', ['id' => $code->id]);

        $this->travel(31)->minutes();

        $this->assertNull(app(PasswordResetService::class)->resetPassword($confirmation->token, 'new-password-123'));
        $this->assertNull(app(PasswordResetService::class)->findValidToken($confirmation->token));
    }
}
