<?php

namespace Tests\Unit\Marketing;

use RuntimeException;
use Tests\TestCase;
use App\Services\Marketing\EmailRateLimiter;

class EmailRateLimiterTest extends TestCase
{
    private EmailRateLimiter $limiter;

    /** 固定「当前分钟」时间戳，避免与真实时钟耦合 */
    private int $base;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.marketing.email.initial_per_minute' => 3,
            'notifications.marketing.email.min_per_minute' => 1,
            'notifications.marketing.email.max_per_minute' => 6,
            'notifications.marketing.email.cooldown_seconds' => 600,
            'notifications.marketing.email.raise_after_successes' => 2,
            'notifications.marketing.email.raise_step' => 1,
            'notifications.marketing.email.penalty_factor' => 0.5,
            'notifications.marketing.email.rate_limit_markers' => ['421', '450', 'rate limit', 'too many'],
        ]);

        $this->limiter = new EmailRateLimiter();
        $this->limiter->reset();

        $this->base = strtotime('2026-01-01 10:00:00 UTC');
    }

    public function test_initial_limit_follows_config(): void
    {
        $this->assertSame(3, $this->limiter->currentLimit());
        $this->assertSame(3, $this->limiter->availableBudget($this->base));
    }

    public function test_acquire_allows_up_to_initial_limit_then_denies(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($this->limiter->acquire($this->base));
        }

        $this->assertFalse($this->limiter->acquire($this->base));
        $this->assertSame(0, $this->limiter->availableBudget($this->base));
    }

    public function test_window_resets_on_new_minute(): void
    {
        $this->assertTrue($this->limiter->acquire($this->base));
        $this->assertSame(1, $this->limiter->currentWindowCount($this->base));

        $nextMinute = $this->base + 60;
        $this->assertSame(0, $this->limiter->currentWindowCount($nextMinute));
        $this->assertTrue($this->limiter->acquire($nextMinute));
    }

    public function test_penalty_halves_limit_and_starts_cooldown(): void
    {
        $this->assertSame(3, $this->limiter->currentLimit());

        $rateLimitedAt = $this->base + 120;
        $this->limiter->penalize($rateLimitedAt);

        // floor(3 * 0.5) = 1，且不得低于 min_per_minute
        $this->assertSame(1, $this->limiter->currentLimit());
        $this->assertTrue($this->limiter->isCoolingDown($rateLimitedAt));
        $this->assertFalse($this->limiter->acquire($rateLimitedAt));

        // 冷却窗口结束后恢复发送（配额仍保持降低后的值，需要成功记录逐步爬升）
        $afterCooldown = $rateLimitedAt + 601;
        $this->assertFalse($this->limiter->isCoolingDown($afterCooldown));
        $this->assertTrue($this->limiter->acquire($afterCooldown));
    }

    public function test_consecutive_successes_raise_limit_stepwise(): void
    {
        $this->assertSame(3, $this->limiter->currentLimit());

        $this->limiter->recordSuccess($this->base);
        $this->limiter->recordSuccess($this->base);

        $this->assertSame(4, $this->limiter->currentLimit());

        $this->limiter->recordSuccess($this->base);
        $this->limiter->recordSuccess($this->base);

        $this->assertSame(5, $this->limiter->currentLimit());
    }

    public function test_limit_never_exceeds_maximum(): void
    {
        config([
            'notifications.marketing.email.initial_per_minute' => 6,
            'notifications.marketing.email.raise_after_successes' => 1,
        ]);

        $this->assertSame(6, $this->limiter->currentLimit());

        $this->limiter->recordSuccess($this->base);

        // 已达 max，不允许继续上调
        $this->assertSame(6, $this->limiter->currentLimit());
    }

    public function test_penalty_never_drops_below_minimum(): void
    {
        config([
            'notifications.marketing.email.initial_per_minute' => 2,
            'notifications.marketing.email.min_per_minute' => 2,
        ]);

        $this->limiter->penalize($this->base);

        $this->assertSame(2, $this->limiter->currentLimit());
    }

    public function test_rate_limit_marker_detection(): void
    {
        $this->assertTrue($this->limiter->isRateLimitError(new RuntimeException('421 4.7.29 Too many emails sent per day')));
        $this->assertTrue($this->limiter->isRateLimitError(new RuntimeException('450 4.7.1 sorry, too fast')));
        $this->assertTrue($this->limiter->isRateLimitError(new RuntimeException('observed rate limit exceeded')));

        // 非限流类错误不应误判
        $this->assertFalse($this->limiter->isRateLimitError(new RuntimeException('550 5.1.1 User unknown')));
        $this->assertFalse($this->limiter->isRateLimitError(new RuntimeException('connect timed out')));
    }
}