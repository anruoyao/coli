<?php

namespace App\Services\User;

use App\Models\PresenceSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * 在线状态服务（presence_sessions + Redis 计数）。
 *
 * 职责：
 *  1. touch(): 上报一次在线（会话 upsert + users.last_active 兜底 + Redis sorted set 计数）
 *  2. onlineCounts(): 在线计数（Redis ZCOUNT O(1)，Redis 不可用降级 DB 查询）
 *
 * 在线窗口见 config('user.online_interval_in_minutes')；
 * 同会话（user_id + client_id）写入节流见 config('user.presence_throttle_seconds')，
 * 前后台状态切换不受节流限制（fg/bg 切换必须立即生效）。
 */
class PresenceService
{
    public const REDIS_NS = 'presence:online';

    /**
     * 上报一次在线。data 支持：client_id / platform(web|android|ios) /
     * platform_detail / is_background / ip_address(country,region,city,timezone 可选透传)。
     */
    public function touch(User $user, array $data): PresenceSession
    {
        $now        = now();
        $clientId   = ! empty($data['client_id']) ? (string) $data['client_id'] : (string) Str::uuid();
        $platform   = in_array($data['platform'] ?? '', ['web', 'android', 'ios']) ? $data['platform'] : 'web';
        $background = (bool) ($data['is_background'] ?? false);

        $session = PresenceSession::query()
            ->where('user_id', $user->id)
            ->where('client_id', $clientId)
            ->first();

        // 节流：同状态且窗口内不重复写（前后台状态变化时跳过，保证立即生效）
        $throttle = (int) config('user.presence_throttle_seconds', 60);
        if ($session && (bool) $session->is_background === $background
            && $session->last_seen_at
            && $session->last_seen_at->diffInSeconds($now, absolute: true) < $throttle) {
            return $session;
        }

        $geo = $this->resolveGeo($user, $data);

        if (! $session) {
            $session = PresenceSession::query()->create(array_merge([
                'user_id'        => $user->id,
                'client_id'      => $clientId,
                'platform'       => $platform,
                'started_at'     => $now,
                'last_seen_at'   => $now,
                'is_background'  => $background,
            ], $geo));
        } else {
            $session->update(array_merge([
                'platform'           => $platform,
                'platform_detail'    => $data['platform_detail'] ?? $session->platform_detail,
                'last_seen_at'       => $now,
                'is_background'      => $background,
                'ip_address'         => $session->ip_address ?: ($geo['ip_address'] ?? null),
            ], $geo));
        }

        // 用户级兜底（维持既有 isOnline() 语义，5 分钟节流）
        if ($user->last_active < $now->subMinutes((int) config('user.online_interval_in_minutes', 5))) {
            $user->last_active = $now;
            $user->saveQuietly();
        }

        $this->syncCounter($session, $platform, $background, $now);

        return $session;
    }

    /**
     * 在线计数（按平台）：['web'=>n, 'android'=>n, 'ios'=>n, 'total'=>n]。
     * Redis sorted set（member = user_id:client_id，score = unix 时间戳）O(1) 求和；
     * Redis 异常时降级 DB 实时统计。
     */
    public function onlineCounts(): array
    {
        $min = now()->subMinutes((int) config('user.online_interval_in_minutes', 5))->getTimestamp();
        $max = now()->getTimestamp();

        try {
            $total  = 0;
            $counts = [];
            foreach (array_keys(PresenceSession::PLATFORM) as $platform) {
                $n = (int) Redis::zcount(static::redisKey($platform), $min, $max);
                $counts[$platform] = $n;
                $total += $n;
            }
            $counts['total'] = $total;
        } catch (\Throwable $e) {
            $counts = $this->dbCounts();
        }

        return $counts;
    }

    public static function redisKey(string $platform): string
    {
        return static::REDIS_NS . ':' . $platform;
    }

    protected function dbCounts(): array
    {
        $counts = ['web' => 0, 'android' => 0, 'ios' => 0, 'total' => 0];

        PresenceSession::query()->online()->pluck('platform')->each(function ($platform) use (&$counts) {
            $counts[$platform] = ($counts[$platform] ?? 0) + 1;
            $counts['total']++;
        });

        return $counts;
    }

    /**
     * Redis 计数同步：前台 ZADD 该会话成员 + 机会式清理过期成员；后台离席 ZREM 即刻下线。
     */
    protected function syncCounter(PresenceSession $session, string $platform, bool $background, $now): void
    {
        $key    = static::redisKey($platform);
        $member = $session->user_id . ':' . $session->client_id;

        try {
            if ($background) {
                Redis::zrem($key, $member);
            } else {
                Redis::zadd($key, $now->getTimestamp(), $member);

                $windowMin = now()
                    ->subMinutes((int) config('user.online_interval_in_minutes', 5))
                    ->getTimestamp();
                Redis::zremrangebyscore($key, 0, $windowMin);
            }
        } catch (\Throwable $e) {
            // Redis 不可用：静默，计数走 DB 降级（临时打印真实原因便于排查）
            Log::error('presence syncCounter failed: ' . $e->getMessage(), [
                'key' => $key, 'member' => $member,
            ]);
        }
    }

    /**
     * 地理位置：优先透传；否则复用 devices 表已采集数据（避免每次请求调用外部地理服务）。
     */
    protected function resolveGeo(User $user, array $data): array
    {
        $fields = ['ip_address', 'country', 'region', 'city', 'timezone'];
        $geo    = [];

        foreach ($fields as $field) {
            if (! empty($data[$field])) {
                $geo[$field] = $data[$field];
            }
        }

        if (empty($geo['ip_address'])) {
            $device = $user->devices()->latest('last_online')->first($fields);
            if ($device) {
                foreach ($fields as $field) {
                    if (empty($geo[$field]) && ! empty($device->{$field})) {
                        $geo[$field] = $device->{$field};
                    }
                }
            }
        }

        return $geo;
    }
}