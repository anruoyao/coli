<?php

namespace Tests\Feature\Api\Throttle;

use App\Models\ApiThrottleEvent;

/**
 * 429 统一响应测试（bootstrap/app.php withExceptions 渲染）。
 *
 * 覆盖：统一 JSON 结构、Retry-After / X-RateLimit-* 头透传、事件落库（含分类与用户维度）。
 */
class Throttle429ResponseTest extends ThrottleTestCase
{
    public function test_throttle_429_returns_unified_json_and_headers(): void
    {
        config(['security.rate_limits.timeline' => ['max' => 2, 'decay' => 1]]);

        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/timeline/feed')->assertOk();
        $this->getJson('/api/timeline/feed')->assertOk();

        $response = $this->getJson('/api/timeline/feed');

        $response->assertStatus(429)
            ->assertJson([
                'status' => 'error',
                'code'   => 429,
            ])
            ->assertJsonStructure(['status', 'code', 'message']);

        // 框架 throttle 异常自带的标准头必须透传
        $this->assertNotEmpty($response->headers->get('Retry-After'));
        $this->assertNotEmpty($response->headers->get('X-RateLimit-Limit'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Reset'));

        // message 带剩余等待秒数（Web/App 直接展示该文案）
        $this->assertSame(
            __('api/error.throttle_seconds', ['seconds' => (int) $response->headers->get('Retry-After')]),
            $response->json('message')
        );
    }

    public function test_throttle_429_event_is_recorded_for_authenticated_user(): void
    {
        config(['security.rate_limits.timeline' => ['max' => 1, 'decay' => 1]]);

        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/timeline/feed')->assertOk();
        $this->getJson('/api/timeline/feed')->assertStatus(429);

        $event = ApiThrottleEvent::query()
            ->where('dimension', ApiThrottleEvent::DIMENSION_USER)
            ->where('identifier', (string) $user->id)
            ->where('category', 'throttle:timeline')
            ->first();

        $this->assertNotNull($event, '429 事件应按用户维度落库');
        $this->assertSame(1, $event->limit);
        $this->assertSame('api/timeline/feed', $event->path);
        $this->assertSame('GET', $event->method);
        $this->assertNotNull($event->ip_address);
    }

    public function test_throttle_429_event_is_recorded_for_guest_as_ip_dimension(): void
    {
        // 公开组（无 auth）：未登录请求按 IP 维度落库
        config(['security.rate_limits.translations' => ['max' => 1, 'decay' => 1]]);

        $this->getJson('/api/translations/app')->assertOk();
        $this->getJson('/api/translations/app')->assertStatus(429);

        $this->assertDatabaseHas(ApiThrottleEvent::getModel()->getTable(), [
            'dimension' => ApiThrottleEvent::DIMENSION_IP,
            'category'  => 'throttle:translations',
        ]);
    }
}
