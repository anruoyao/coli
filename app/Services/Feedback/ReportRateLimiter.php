<?php

namespace App\Services\Feedback;

use App\Models\Report;
use Carbon\Carbon;

/**
 * 举报限流器（账号 + IP 双维度）。
 *
 * 计数直接来源 reports 表（含 ip_address 列），落库即持久化：
 * 系统重启 / 缓存清空都不会丢失限流状态。窗口为滑动窗口（默认 24 小时），
 * 窗口内同一用户或同一 IP 累计提交超过上限（默认 10 次）即拦截。
 */
class ReportRateLimiter
{
    public const CONFIG_KEY = 'security.reports';

    /**
     * 校验是否允许提交举报。
     *
     * @return array|null 允许时返回 null；超限时返回
     *                    ['dimension' => 'user'|'ip', 'retry_after' => 秒, 'limit' => int, 'window_hours' => int]
     */
    public function check(int $userId, string $ipAddress): ?array
    {
        $limit = $this->limit();
        $windowStart = now()->subHours($this->windowHours());

        // 账号维度
        $userCount = Report::query()
            ->where('reporter_id', $userId)
            ->where('created_at', '>=', $windowStart)
            ->count();

        if($userCount >= $limit) {
            return [
                'dimension' => 'user',
                'retry_after' => $this->retryAfterFor('reporter_id', $userId, $windowStart),
                'limit' => $limit,
                'window_hours' => $this->windowHours(),
            ];
        }

        // IP 维度
        $ipCount = Report::query()
            ->where('ip_address', $ipAddress)
            ->where('created_at', '>=', $windowStart)
            ->count();

        if($ipCount >= $limit) {
            return [
                'dimension' => 'ip',
                'retry_after' => $this->retryAfterFor('ip_address', $ipAddress, $windowStart),
                'limit' => $limit,
                'window_hours' => $this->windowHours(),
            ];
        }

        return null;
    }

    /**
     * 当前用户窗口内剩余可提交次数（成功响应中带回，供客户端展示配额）。
     */
    public function remaining(int $userId): int
    {
        $count = Report::query()
            ->where('reporter_id', $userId)
            ->where('created_at', '>=', now()->subHours($this->windowHours()))
            ->count();

        return max(0, $this->limit() - $count);
    }

    /**
     * 距离下一次可举报的秒数：窗口内最早一条记录滑出窗口的时刻。
     */
    private function retryAfterFor(string $column, int|string $value, $windowStart): int
    {
        $earliest = Report::query()
            ->where($column, $value)
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at')
            ->first('created_at');

        if(! $earliest) {
            return 0;
        }

        // reports.created_at 为自定义 DateFormatter cast（无 Carbon 方法）。
        // 与 DateFormatter 内部一致地按默认时区解析字符串（勿加显式 'UTC'，否则与 now() 产生偏移）。
        $availableAt = Carbon::parse($earliest->created_at->getTimestamp())
            ->addHours($this->windowHours());

        return max(0, now()->diffInSeconds($availableAt));
    }

    public function limit(): int
    {
        return max(1, (int) config(self::CONFIG_KEY . '.max_per_day', 10));
    }

    public function windowHours(): int
    {
        return max(1, (int) config(self::CONFIG_KEY . '.window_hours', 24));
    }
}
