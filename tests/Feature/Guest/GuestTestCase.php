<?php

namespace Tests\Feature\Guest;

use App\Enums\User\UserStatus;
use App\Models\Currency;
use App\Models\Post;
use App\Models\User;
use App\Settings\GuestSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 访客测试基类：迁移全新库 + 访客测试数据工厂。
 */
abstract class GuestTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * 访客 API 同样受 X-App-Key 准入约束（VerifyAppKey，缺失返回 404），
     * 取值与 tests/Feature/Api/Auth/RegistrationEmailVerificationTest 一致。
     */
    private const APP_KEY = 'clbPK-8f3k2m9xq4w7v1t6a5s0d2n8h4j6y1c';

    private static int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // 默认头对本类发出的全部请求（含 getJson/withUnencryptedCookie 链）生效。
        $this->withHeader('X-App-Key', self::APP_KEY);

        // SPA shell / SEO 视图的 default_currency() 依赖 world_currencies 基础数据，
        // 全新迁移的测试库默认没有任何币种。
        Currency::query()->create([
            'alpha_3_code' => (string) config('app.default_currency', 'USD'),
            'name'         => 'US Dollar',
            'symbol'       => '$',
            'symbol_native' => '$',
            'status'       => true,
        ]);
        Cache::forget('world_currencies');
    }

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
            'last_name'  => 'User',
            'username'   => 'test_'.self::$sequence.'_'.substr(uniqid(), -6),
            'email'      => 'test_'.self::$sequence.'_'.substr(uniqid(), -6).'@example.com',
            'password'   => 'hashed-password',
            'status'     => UserStatus::ACTIVE->value,
            'tips'       => [],
            'website'    => 'https://example.com',
            'phone'      => '+10000000',
            'bio'        => 'A test bio.',
        ], $attributes));
    }

    protected function makePost(User $user, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $user->id,
            'content' => 'Hello public world '.uniqid(),
            'type'    => 'text',
            'status'  => 'active',
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
