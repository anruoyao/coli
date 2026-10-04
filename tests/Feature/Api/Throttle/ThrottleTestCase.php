<?php

namespace Tests\Feature\Api\Throttle;

use App\Enums\User\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 限流测试基类。
 *
 * 约定（见 TESTING.md）：
 * - DatabaseTransactions（禁 RefreshDatabase，75+ 表全量迁移单方法 4 分钟+）
 * - 关闭 X-App-Key 准入门槛（缺失该头返回 404 伪装）
 * - CACHE_STORE=array（phpunit.xml）：限流计数走 array store，
 *   测试方法内累加有效，Cache::flush() 可整体重置
 */
abstract class ThrottleTestCase extends TestCase
{
    use DatabaseTransactions;

    private static int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.app_key.enabled' => false]);

        // 每个用例独立计数，避免限流桶跨用例残留
        Cache::flush();
    }

    protected function makeUser(array $attributes = []): User
    {
        self::$sequence++;

        return User::create(array_merge([
            'first_name' => 'Throttle',
            'last_name'  => 'Tester',
            'username'   => 'throttle_'.self::$sequence.'_'.substr(uniqid(), -6),
            'email'      => 'throttle_'.self::$sequence.'_'.substr(uniqid(), -6).'@example.com',
            'password'   => 'hashed-password',
            'status'     => UserStatus::ACTIVE->value,
            'tips'       => [],
            'website'    => 'https://example.com',
            'phone'      => '+10000000',
            'bio'        => 'A test bio.',
            // 默认老账号：避开 AbuseGuard 新账号（24h）更严限额的干扰，
            // 需要测试新账号限额的用例自行覆盖 created_at
            'created_at' => now()->subDays(30)->format('Y-m-d H:i:s'),
        ], $attributes));
    }
}
