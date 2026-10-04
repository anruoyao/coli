<?php

namespace Tests\Feature\Api\Throttle;

use App\Models\ApiThrottleEvent;

/**
 * AbuseGuard 风控动作限流测试（修复路径匹配 bug 后的动作生效验证 + 新增高成本动作）。
 *
 * 覆盖：
 * - 路径匹配修复（相对 /api/ 的 paths 现在能匹配到 api/ 前缀的真实请求路径）
 * - video-upload 双窗口（短桶 + 24h 硬上限桶）
 * - wallet-transfer 资金动作限流
 * - email-change 敏感资料变更限流 + 事件落库
 */
class AbuseGuardActionsTest extends ThrottleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 收窄动作额度，避免测试循环过多请求
        config([
            'security.actions.video-upload' => [
                'paths' => ['post/editor/media/video/upload'],
                'max' => 1, 'decay' => 600,
                'max_hard' => 2, 'decay_hard' => 86400,
            ],
            'security.actions.wallet-transfer' => [
                'paths' => ['wallet/transfer'],
                'max' => 2, 'decay' => 60,
            ],
            'security.actions.email-change' => [
                'paths' => ['settings/email/update'],
                'max' => 2, 'decay' => 3600,
            ],
        ]);
    }

    public function test_video_upload_is_limited_by_primary_bucket(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        // 第 1 次放行（后续控制器层校验，非 429），第 2 次被 10 分钟短桶拦截
        $first = $this->postJson('/api/post/editor/media/video/upload');
        $this->assertNotSame(429, $first->status(), '首次上传不应被限流拦截');

        $second = $this->postJson('/api/post/editor/media/video/upload');
        $second->assertStatus(429)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 429);

        $this->assertSame('1', $second->headers->get('X-RateLimit-Limit'));
        $this->assertNotEmpty($second->headers->get('Retry-After'));
    }

    public function test_video_upload_hard_bucket_limits_daily_volume(): void
    {
        // 主桶放宽（5 次），硬上限桶 2 次：第 3 次被 24h 硬桶拦截
        config(['security.actions.video-upload' => [
            'paths' => ['post/editor/media/video/upload'],
            'max' => 5, 'decay' => 600,
            'max_hard' => 2, 'decay_hard' => 86400,
        ]]);

        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/post/editor/media/video/upload');
        $this->postJson('/api/post/editor/media/video/upload');

        $third = $this->postJson('/api/post/editor/media/video/upload');
        $third->assertStatus(429);
        // 硬桶：limit=2 / 窗口 86400 秒
        $this->assertSame('2', $third->headers->get('X-RateLimit-Limit'));
    }

    public function test_wallet_transfer_is_rate_limited(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/wallet/transfer');
        $this->postJson('/api/wallet/transfer');

        $this->postJson('/api/wallet/transfer')->assertStatus(429);
    }

    public function test_email_change_is_rate_limited_and_recorded(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/settings/email/update');
        $this->putJson('/api/settings/email/update');

        $this->putJson('/api/settings/email/update')->assertStatus(429);

        // AbuseGuard 拦截事件落库
        $this->assertDatabaseHas(ApiThrottleEvent::getModel()->getTable(), [
            'dimension' => ApiThrottleEvent::DIMENSION_USER,
            'identifier' => (string) $user->id,
            'category'  => 'abuse:email-change',
            'action'    => 'email-change',
        ]);
    }

    public function test_abuse_guard_actions_apply_to_new_users_with_stricter_limits(): void
    {
        // 新账号（24h 内）：wallet-transfer new_user_max=1
        config(['security.actions.wallet-transfer' => [
            'paths' => ['wallet/transfer'],
            'max' => 5, 'decay' => 60,
            'new_user_max' => 1, 'new_user_decay' => 60,
        ]]);

        $newUser = $this->makeUser(['created_at' => now()->subHours(2)->format('Y-m-d H:i:s')]);
        $this->actingAs($newUser, 'sanctum');

        $this->postJson('/api/wallet/transfer');

        // 第 2 次即被新账号限额拦截（老账号为 5）
        $this->postJson('/api/wallet/transfer')->assertStatus(429);
    }

    public function test_abuse_guard_429_shows_unified_message(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/wallet/transfer');
        $this->postJson('/api/wallet/transfer');

        $response = $this->postJson('/api/wallet/transfer');

        $response->assertStatus(429);
        $this->assertSame(
            __('api/error.throttle_seconds', ['seconds' => (int) $response->headers->get('Retry-After')]),
            $response->json('message'),
            'AbuseGuard 429 文案应与全局 throttle 渲染统一（带等待秒数）'
        );
    }
}
