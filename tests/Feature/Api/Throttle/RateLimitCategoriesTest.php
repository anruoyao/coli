<?php

namespace Tests\Feature\Api\Throttle;

/**
 * 分类限流（throttle:api.{category}）行为测试。
 *
 * 覆盖：分类额度生效、用户维度独立计数（两用户同 IP 互不影响）、
 * 差异化额度（ai 高成本组收紧）。
 */
class RateLimitCategoriesTest extends ThrottleTestCase
{
    public function test_timeline_category_limit_applies_per_user(): void
    {
        config(['security.rate_limits.timeline' => ['max' => 3, 'decay' => 1]]);

        $userA = $this->makeUser();
        $userB = $this->makeUser();

        // 用户 A 打满 3 次
        $this->actingAs($userA, 'sanctum');
        $this->getJson('/api/timeline/feed')->assertOk();
        $this->getJson('/api/timeline/feed')->assertOk();
        $this->getJson('/api/timeline/feed')->assertOk();

        // 第 4 次：429
        $this->getJson('/api/timeline/feed')->assertStatus(429);

        // 用户 B（同 IP 同测试进程）：独立计数，不受 A 影响
        $this->actingAs($userB, 'sanctum');
        $this->getJson('/api/timeline/feed')->assertOk();
    }

    public function test_ai_category_has_stricter_limit(): void
    {
        config(['security.rate_limits.ai' => ['max' => 2, 'decay' => 1]]);

        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/ai/greeting-message');
        $this->getJson('/api/ai/greeting-message');

        $this->getJson('/api/ai/greeting-message')->assertStatus(429);
    }

    public function test_different_categories_have_independent_buckets(): void
    {
        config([
            'security.rate_limits.timeline' => ['max' => 2, 'decay' => 1],
        ]);

        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        // timeline 打满
        $this->getJson('/api/timeline/feed')->assertOk();
        $this->getJson('/api/timeline/feed')->assertOk();
        $this->getJson('/api/timeline/feed')->assertStatus(429);

        // 其他分类（profile，未改配置 60/min）不受 timeline 桶影响
        $this->getJson('/api/profile/profile')->assertOk();
    }
}
