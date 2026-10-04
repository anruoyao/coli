<?php

namespace Tests\Unit\Throttle;

use App\Models\ApiThrottleEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * ApiThrottleEvent 模型 record() 与裁剪命令单元测试。
 */
class ApiThrottleEventTest extends TestCase
{
    use DatabaseTransactions;

    public function test_record_captures_request_context(): void
    {
        $response = $this->getJson('/api/system/version/check');

        // 在请求生命周期内 record：模拟中间件/异常渲染处调用
        ApiThrottleEvent::record(
            ApiThrottleEvent::DIMENSION_IP,
            '127.0.0.1',
            'global-ip-gate',
            null,
            600,
            60
        );

        $event = ApiThrottleEvent::query()->latest('id')->first();

        $this->assertSame('ip', $event->dimension);
        $this->assertSame('127.0.0.1', $event->identifier);
        $this->assertSame('global-ip-gate', $event->category);
        $this->assertSame(600, $event->limit);
        $this->assertSame(60, $event->window_seconds);
        // path/method/ip/ua 从当前 request 内部提取
        $this->assertSame('api/system/version/check', $event->path);
        $this->assertSame('GET', $event->method);
        $this->assertNotNull($event->ip_address);
        $this->assertNotNull($event->created_at);
    }

    public function test_record_outside_request_context_handles_null_request(): void
    {
        // 无请求上下文（如队列/命令内）时 path/method/ip 为 null，不抛异常
        $event = ApiThrottleEvent::record(
            ApiThrottleEvent::DIMENSION_USER,
            '42',
            'throttle:timeline',
            null,
            240,
            60
        );

        $this->assertNotNull($event->id);
        $this->assertSame('user', $event->dimension);
        $this->assertSame('42', $event->identifier);
    }

    public function test_prune_command_deletes_expired_events(): void
    {
        ApiThrottleEvent::create([
            'dimension' => 'ip',
            'identifier' => '1.2.3.4',
            'category' => 'global-ip-gate',
            'created_at' => now()->subDays(8),
        ]);

        ApiThrottleEvent::create([
            'dimension' => 'ip',
            'identifier' => '5.6.7.8',
            'category' => 'global-ip-gate',
            'created_at' => now()->subDays(1),
        ]);

        $this->artisan('colibri:prune-throttle-events', ['--days' => 7])
            ->expectsOutputToContain('Pruned 1 throttle events')
            ->assertSuccessful();

        $this->assertDatabaseMissing(ApiThrottleEvent::getModel()->getTable(), [
            'identifier' => '1.2.3.4',
        ]);

        $this->assertDatabaseHas(ApiThrottleEvent::getModel()->getTable(), [
            'identifier' => '5.6.7.8',
        ]);
    }
}
