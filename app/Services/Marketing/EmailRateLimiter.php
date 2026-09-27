<?php

namespace App\Services\Marketing;

use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;

/**
 * 营销邮件智能限速器（QQ SMTP 场景专用）。
 *
 * 原理：
 *  - 以「当前自然分钟」为窗口维护已发送计数，配合分布式锁保证多 worker 原子性；
 *  - 动态配额 currentLimit（存于缓存）以 initial_per_minute 起步，命中限流时
 *    penalize() 半减封顶值并进入 cooldown 冷却窗口（暂停发送），
 *    稳定成功一定数量后 recordSuccess() 逐步爬升回 max_per_minute；
 *  - acquire() 每封邮件发送前调用，返回 false 表示当前无配额（Job 应延迟释放重试）。
 *
 * 缓存 store 默认跟随系统默认缓存（生产 observer 建议 database/redis），
 * 测试环境为 array；通过构造函数可注入任意 Repository 便于单测。
 */
class EmailRateLimiter
{
    protected const PREFIX = 'mkt:mail:';

    protected Repository $cache;

    public function __construct(?Repository $cache = null)
    {
        $this->cache = $cache ?? Cache::store();
    }

    // ------------------------------------------------------------------
    // 缓存键
    // ------------------------------------------------------------------
    protected function windowKey(int $timestamp): string
    {
        return self::PREFIX.'count:'.date('YmdHi', $timestamp);
    }

    protected const KEY_LIMIT = self::PREFIX.'limit';
    protected const KEY_COOLDOWN = self::PREFIX.'cool_until';
    protected const KEY_SUCCESSES = self::PREFIX.'successes';

    protected function lock(): \Illuminate\Contracts\Cache\Lock
    {
        return $this->cache->lock(self::PREFIX.'lock', 10);
    }

    // ------------------------------------------------------------------
    // 配置读数
    // ------------------------------------------------------------------
    protected function config(string $key, mixed $default = null): mixed
    {
        return config("notifications.marketing.email.{$key}", $default);
    }

    public function minLimit(): int
    {
        return max(1, (int) $this->config('min_per_minute', 15));
    }

    public function maxLimit(): int
    {
        return max($this->minLimit(), (int) $this->config('max_per_minute', 60));
    }

    /**
     * 当前动态配额（首次读取时初始化为 initial_per_minute）。
     */
    public function currentLimit(): int
    {
        $limit = $this->cache->get(self::KEY_LIMIT);

        if ($limit === null) {
            $limit = (int) $this->config('initial_per_minute', 30);
            $this->cache->forever(self::KEY_LIMIT, (int) $limit);
        }

        return max($this->minLimit(), (int) $limit);
    }

    public function setLimit(int $limit): void
    {
        $this->cache->forever(self::KEY_LIMIT, max($this->minLimit(), min($this->maxLimit(), $limit)));
    }

    /**
     * 当前窗口已发送数量。
     */
    public function currentWindowCount(?int $now = null): int
    {
        $now = $now ?? time();

        return (int) $this->cache->get($this->windowKey($now), 0);
    }

    /**
     * 当前窗口剩余可发送数量（冷却中返回 0）。
     */
    public function availableBudget(?int $now = null): int
    {
        $now = $now ?? time();

        if ($this->isCoolingDown($now)) {
            return 0;
        }

        return max(0, $this->currentLimit() - $this->currentWindowCount($now));
    }

    /**
     * 是否处于 SMTP 限流冷却窗口。
     */
    public function isCoolingDown(?int $now = null): bool
    {
        $now = $now ?? time();

        $coolUntil = (int) $this->cache->get(self::KEY_COOLDOWN, 0);

        return $now < $coolUntil;
    }

    /**
     * 尝试占用一个发送配额。
     *
     * @return bool 成功占用返回 true；冷却中或无配额返回 false。
     */
    public function acquire(?int $now = null): bool
    {
        $now = $now ?? time();

        $lock = $this->lock();

        if (! $lock->acquire()) {
            return false;
        }

        try {
            if ($this->isCoolingDown($now)) {
                return false;
            }

            $key = $this->windowKey($now);
            $count = (int) $this->cache->get($key, 0);
            $limit = $this->currentLimit();

            if ($count >= $limit) {
                return false;
            }

            // 持锁期间 put 原子递增并天然带 TTL（当前分钟结束 + 缓冲），
            // 兼容 redis/database/array 等各类缓存 store。
            $this->cache->put($key, $count + 1, 180);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * 发送成功后调用：维护连续成功计数，达标后向上提升配额。
     */
    public function recordSuccess(?int $now = null): void
    {
        $now = $now ?? time();

        $lock = $this->lock();

        if (! $lock->acquire()) {
            return;
        }

        try {
            $streak = (int) $this->cache->get(self::KEY_SUCCESSES, 0) + 1;

            $this->cache->forever(self::KEY_SUCCESSES, $streak);

            $raiseAfter = max(1, (int) $this->config('raise_after_successes', 25));
            $raiseStep = max(1, (int) $this->config('raise_step', 1));

            if ($streak >= $raiseAfter) {
                $this->setLimit($this->currentLimit() + $raiseStep);
                $this->cache->forever(self::KEY_SUCCESSES, 0);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * 命中 SMTP 限流后调用：半减配额并开启冷却窗口（暂停发送直至冷却结束）。
     */
    public function penalize(?int $now = null, ?int $cooldownSeconds = null): void
    {
        $now = $now ?? time();
        $cooldownSeconds = $cooldownSeconds ?? (int) $this->config('cooldown_seconds', 600);

        $lock = $this->lock();

        if (! $lock->acquire()) {
            return;
        }

        try {
            $factor = (float) $this->config('penalty_factor', 0.5);

            $this->setLimit((int) floor($this->currentLimit() * $factor));

            $this->cache->put(self::KEY_COOLDOWN, $now + max(30, $cooldownSeconds), now()->addSeconds(max(30, $cooldownSeconds) + 60));

            $this->cache->forever(self::KEY_SUCCESSES, 0);
        } finally {
            $lock->release();
        }
    }

    /**
     * 根据异常信息判断是否为 SMTP 限流类错误（QQ 官方会返回 421/450/451/452 等）。
     */
    public function isRateLimitError(Throwable $e): bool
    {
        $message = strtolower((string) $e->getMessage());

        foreach ((array) $this->config('rate_limit_markers', []) as $marker) {
            if (str_contains($message, strtolower((string) $marker))) {
                return true;
            }
        }

        return false;
    }

    /**
     * 手工恢复初始配额（管理员操作、测试用）。
     */
    public function reset(): void
    {
        $this->cache->forget(self::KEY_LIMIT);
        $this->cache->forget(self::KEY_COOLDOWN);
        $this->cache->forget(self::KEY_SUCCESSES);
    }
}