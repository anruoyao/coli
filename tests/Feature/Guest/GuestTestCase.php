<?php

namespace Tests\Feature\Guest;

use App\Enums\User\UserStatus;
use App\Models\Post;
use App\Models\User;
use App\Settings\GuestSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 访客测试基类：迁移全新库 + 访客测试数据工厂。
 */
abstract class GuestTestCase extends TestCase
{
    use RefreshDatabase;

    private static int $sequence = 0;

    protected function enableGuestMode(): void
    {
        $settings = app(GuestSettings::class);
        $settings->enabled = true;
        $settings->save();
    }

    protected function makeUser(array $attributes = []): User
    {
        self::$sequence++;

        return User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'name' => 'Test User',
            'username' => 'test_'.self::$sequence.'_'.substr(uniqid(), -6),
            'email' => 'test_'.self::$sequence.'_'.substr(uniqid(), -6).'@example.com',
            'password' => 'hashed-password',
            'status' => UserStatus::ACTIVE->value,
            'tips' => [],
            'website' => 'https://example.com',
            'phone' => '+10000000',
            'bio' => 'A test bio.',
        ], $attributes));
    }

    protected function makePost(User $user, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'content' => 'Hello public world '.uniqid(),
            'type' => 'text',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * 访客 API 公共请求（带 JSON 头）。
     */
    protected function guestGet(string $uri, array $headers = [])
    {
        return $this->getJson('/api/guest/v1/'.$uri, $headers);
    }
}
