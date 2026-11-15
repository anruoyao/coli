<?php

namespace App\Services\Auth;

use App\Models\EmailConfirmation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function issue(string $email): EmailConfirmation
    {
        EmailConfirmation::query()
            ->where('email', $email)
            ->whereNull('code')
            ->whereNotNull('expires_at')
            ->delete();

        return EmailConfirmation::create([
            'email' => $email,
            'token' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(max(1, (int) config('security.auth.password_reset_expires_minutes', 30))),
        ]);
    }

    public function findValidToken(string $token, ?string $email = null): ?EmailConfirmation
    {
        return EmailConfirmation::query()
            ->where('token', $token)
            ->whereNull('code')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->when($email !== null, fn ($query) => $query->where('email', $email))
            ->first();
    }

    public function findValidTokenByHashId(string $hashId): ?EmailConfirmation
    {
        return EmailConfirmation::query()
            ->whereHashId($hashId)
            ->whereNull('code')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function resetPassword(string $token, string $password, ?string $email = null): ?User
    {
        return DB::transaction(function () use ($token, $password, $email): ?User {
            $confirmation = EmailConfirmation::query()
                ->where('token', $token)
                ->whereNull('code')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', now())
                ->when($email !== null, fn ($query) => $query->where('email', $email))
                ->lockForUpdate()
                ->first();

            if (! $confirmation) {
                return null;
            }

            $user = User::query()
                ->where('email', $confirmation->email)
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return null;
            }

            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
            EmailConfirmation::where('email', $confirmation->email)->delete();

            return $user;
        });
    }
}
