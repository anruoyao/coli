<?php

namespace Tests\Unit\Feedback;

use Tests\TestCase;
use App\Models\Report;
use App\Models\User;
use App\Enums\Report\ReportType;
use App\Services\Feedback\ReportRateLimiter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Marketing\Concerns\CreatesUsers;

/**
 * 举报限流器单元测试（账号 + IP 双维度、24 小时滑动窗口、持久化计数）。
 *
 * 注：使用 DatabaseTransactions（结构预建后仅事务回滚，逐方法秒级）而非 RefreshDatabase
 *     （Laravel 11 逐方法全量重建库，本项目 75+ 表在 MySQL 上每方法需数十秒）。
 */
class ReportRateLimiterTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private ReportRateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->limiter = app(ReportRateLimiter::class);
    }

    private function createReport(User $reporter, string $ip): Report
    {
        $target = $this->makeUser();

        return $target->reports()->create([
            'reporter_id' => $reporter->id,
            'reason_index' => 0,
            'type' => ReportType::from('user'),
            'ip_address' => $ip,
        ]);
    }

    public function test_allows_submission_under_limit(): void
    {
        $user = $this->makeUser();

        for($i = 0; $i < 9; $i++) {
            $this->createReport($user, "10.0.0.{$i}");
        }

        $this->assertNull($this->limiter->check($user->id, '20.0.0.1'));
        $this->assertSame(1, $this->limiter->remaining($user->id));
    }

    public function test_blocks_user_dimension_at_limit(): void
    {
        $user = $this->makeUser();

        for($i = 0; $i < 10; $i++) {
            $this->createReport($user, "10.0.{$i}.1");
        }

        $result = $this->limiter->check($user->id, '30.0.0.1');

        $this->assertNotNull($result);
        $this->assertSame('user', $result['dimension']);
        $this->assertSame(10, $result['limit']);
        $this->assertSame(24, $result['window_hours']);
        $this->assertGreaterThan(0, $result['retry_after']);
        $this->assertSame(0, $this->limiter->remaining($user->id));
    }

    public function test_blocks_ip_dimension_across_users(): void
    {
        $first = $this->makeUser();
        $second = $this->makeUser();

        // 同一 IP 10 次，分属两个账号各 5 次：账号维度未超限，IP 维度超限
        for($i = 0; $i < 5; $i++) {
            $this->createReport($first, '40.0.0.1');
            $this->createReport($second, '40.0.0.1');
        }

        $result = $this->limiter->check($second->id, '40.0.0.1');

        $this->assertNotNull($result);
        $this->assertSame('ip', $result['dimension']);
    }

    public function test_window_expiry_restores_quota(): void
    {
        $user = $this->makeUser();

        for($i = 0; $i < 10; $i++) {
            $this->createReport($user, "50.0.0.{$i}");
        }

        $this->assertNotNull($this->limiter->check($user->id, '60.0.0.1'));

        // 全部记录滑出 24 小时窗口后配额恢复
        $this->travel(25)->hours();

        $this->assertNull($this->limiter->check($user->id, '60.0.0.1'));
        $this->assertSame(10, $this->limiter->remaining($user->id));
    }

    public function test_retry_after_reflects_earliest_report_sliding_out(): void
    {
        $user = $this->makeUser();

        $this->travel(-23)->hours();

        $this->createReport($user, '70.0.0.1');

        $this->travel(23)->hours();

        for($i = 0; $i < 9; $i++) {
            $this->createReport($user, "70.0.1.{$i}");
        }

        $result = $this->limiter->check($user->id, '80.0.0.1');

        // 最早一条在 23 小时前提交，24 小时窗口 → 约 1 小时（3600 秒）后解禁（travel 时钟存在秒级舍入）
        $this->assertNotNull($result);
        $this->assertGreaterThan(3500, $result['retry_after']);
        $this->assertLessThan(3700, $result['retry_after']);
    }
}
