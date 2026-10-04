<?php

namespace Tests\Feature\Api\Throttle;

use App\Models\ApiThrottleEvent;

/**
 * 全局 IP 闸门（GlobalIpGateMiddleware，L0 防线）测试。
 *
 * 覆盖：每 IP 总量上限、白名单豁免、健康检查路径豁免、429 响应头、事件落库。
 */
class GlobalIpGateTest extends ThrottleTestCase
{
    public function test_ip_gate_blocks_requests_over_global_limit(): void
    {
        config(['security.ip_gate' => ['enabled' => true, 'max_per_minute' => 3, 'whitelist' => []]]);

        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/timeline/feed')->assertOk();
        $this->getJson('/api/timeline/feed')->assertOk();
        $this->getJson('/api/timeline/feed')->assertOk();

        // 第 4 次：全局闸门 429（timeline 组级额度 240/min 未触顶，证明是闸门拦截）
        $response = $this->getJson('/api/timeline/feed');
        $response->assertStatus(429);

        $this->assertNotEmpty($response->headers->get('Retry-After'));
        $this->assertSame('3', $response->headers->get('X-RateLimit-Limit'));
        $this->assertSame('0', $response->headers->get('X-RateLimit-Remaining'));

        // 事件落库：IP 维度 + global-ip-gate 分类
        $this->assertDatabaseHas(ApiThrottleEvent::getModel()->getTable(), [
            'dimension' => ApiThrottleEvent::DIMENSION_IP,
            'category'  => 'global-ip-gate',
        ]);
    }

    public function test_ip_gate_whitelist_bypasses_limit(): void
    {
        // 测试请求 IP 为 127.0.0.1
        config(['security.ip_gate' => ['enabled' => true, 'max_per_minute' => 2, 'whitelist' => ['127.0.0.1']]]);

        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/timeline/feed')->assertOk();
        }
    }

    public function test_ip_gate_exempts_health_check_paths(): void
    {
        config(['security.ip_gate' => ['enabled' => true, 'max_per_minute' => 2, 'whitelist' => []]]);

        // 打满闸门
        $this->getJson('/api/system/version/check');
        $this->getJson('/api/system/version/check');
        $this->getJson('/api/system/version/check');

        // 豁免路径不受闸门限制（正常返回而非 429）
        $response = $this->getJson('/api/system/version/check');
        $this->assertNotSame(429, $response->status());
    }

    public function test_ip_gate_can_be_disabled(): void
    {
        config(['security.ip_gate' => ['enabled' => false, 'max_per_minute' => 1, 'whitelist' => []]]);

        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/timeline/feed')->assertOk();
        }
    }
}
