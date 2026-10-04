<?php

namespace App\Http\Middleware;

use App\Models\ApiThrottleEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * API 滥用防护中间件（P0 防刷数据）。
 *
 * 两层防护（配置见 config/security.php）：
 * 1. 按「用户 × 动作 × 时间窗」计数限流；新账号（注册 < new_user_window_hours）
 *    执行更严格的新人限额。支持可选第二桶（max_hard/decay_hard，长窗口硬上限），
 *    两桶任一超限即拒绝（如视频上传：10 分钟 1 次 + 24 小时 10 次）。
 * 2. 同内容幂等去重：同一用户在同一窗口内对同一动作重复提交相同内容直接拒绝，
 *    拦截脚本连刷帖子 / 评论 / 私信。
 *
 * 拦截时返回统一 429 结构（Retry-After / X-RateLimit-* 头），
 * 并记录 api_throttle_events 事件（后台限流监控页数据来源）。
 *
 * 挂载位置：routes/api.php 各已认证 API 子组的 auth:sanctum 之后
 * （组级中间件在 auth:sanctum 之前执行，拿不到 Sanctum 用户，见 UserOnlineMiddleware
 * 注释，故必须挂在子组内）。未登录请求直接放行，公开接口另有 throttle/WAF 防护。
 */
class AbuseGuardMiddleware
{
    /** 需要做同内容去重的动作 */
    private const DUP_CONTENT_ACTIONS = ['post-create', 'comment-create', 'message-send'];

    /** 每用户每动作的计数 key 前缀 */
    private const CACHE_PREFIX = 'abuse:';

    public function handle(Request $request, Closure $next): Response
    {
        // 与 UserOnlineMiddleware 一致：兼容 web(session) 与 api(sanctum Bearer) 两套认证
        $user = $request->user() ?: $request->user('sanctum');

        if (! $user) {
            return $next($request);
        }

        // 管理员 / 审核角色豁免（role 为 UserRole backed enum，须取 ->value）
        $role = $user->role instanceof \UnitEnum ? ($user->role->value ?? null) : $user->role;
        if (in_array($role, ['root', 'admin', 'moderator'], true)) {
            return $next($request);
        }

        foreach (config('security.actions', []) as $action => $rule) {
            if (! $this->matches($request, $rule)) {
                continue;
            }

            if (in_array($action, self::DUP_CONTENT_ACTIONS, true)) {
                $duplicate = $this->guardDuplicateContent($request, $user->id, $action);
                if ($duplicate) {
                    $window = (int) config('security.duplicate_content_window_seconds', 10);

                    return $this->tooManyResponse($window, null, 'abuse:duplicate-content', $action, $user->id);
                }
            }

            $bucket = $this->guardRateLimit($user->id, $user->created_at, $action, $rule);
            if ($bucket !== null) {
                return $this->tooManyResponse($bucket['decay'], $bucket['max'], 'abuse:' . $action, $action, $user->id);
            }
        }

        return $next($request);
    }

    /**
     * 判断请求是否命中某动作规则（路径前缀/精确匹配 + 可选方法过滤）。
     *
     * paths 配置为相对 /api/ 的路径（如 'post/editor/create'），而 $request->path()
     * 带完整前缀（'api/post/editor/create'）——因此同时匹配带/不带 api/ 前缀两种
     * 写法。历史版本只做裸前缀匹配导致 API 路由永远匹配不上（动作限流失效），
     * 此处兼容两种写法。
     */
    private function matches(Request $request, array $rule): bool
    {
        $path = $request->path(); // 如 api/post/editor/create

        $matched = false;
        foreach ($rule['paths'] ?? [] as $pathRule) {
            $pathRule = trim($pathRule, '/');

            if ($path === $pathRule || str_starts_with($path, $pathRule)
                || $path === 'api/' . $pathRule || str_starts_with($path, 'api/' . $pathRule)) {
                $matched = true;
                break;
            }
        }

        if (! $matched) {
            return false;
        }

        if (! empty($rule['methods']) && ! in_array(strtoupper($request->method()), array_map('strtoupper', $rule['methods']), true)) {
            return false;
        }

        return true;
    }

    /**
     * 按用户 × 动作 × 时间窗计数（主桶 + 可选硬上限桶）。
     * 全部桶通过返回 null（放行）；任一桶超限返回该桶（拒绝，含 max/decay 供响应头）。
     */
    private function guardRateLimit($userId, $createdAt, string $action, array $rule): ?array
    {
        // User.created_at 无 datetime cast（可能为原始字符串），统一转 Carbon 再比较
        $createdAt = $createdAt ? \Illuminate\Support\Carbon::parse($createdAt) : null;

        $isNewUser = $createdAt && $createdAt->gt(now()->subHours((int) config('security.new_user_window_hours', 24)));

        $buckets = [
            [
                'max'   => ($isNewUser && isset($rule['new_user_max'])) ? (int) $rule['new_user_max'] : (int) $rule['max'],
                'decay' => ($isNewUser && isset($rule['new_user_decay'])) ? (int) $rule['new_user_decay'] : (int) $rule['decay'],
                'key'   => self::CACHE_PREFIX . $action . ':u' . $userId,
            ],
        ];

        // 可选第二桶（长窗口硬上限）：新账号沿用同一硬限，不放宽
        if (isset($rule['max_hard'], $rule['decay_hard'])) {
            $buckets[] = [
                'max'   => (int) $rule['max_hard'],
                'decay' => (int) $rule['decay_hard'],
                'key'   => self::CACHE_PREFIX . $action . ':u' . $userId . ':hard',
            ];
        }

        // 先检查所有桶：任一超限即拒绝（不增加计数）
        foreach ($buckets as $bucket) {
            if ((int) Cache::get($bucket['key'], 0) >= $bucket['max']) {
                return $bucket;
            }
        }

        // 全部通过：各桶计数 +1（确保 key 存在并带 TTL，已存在时 add 为空操作，不重置过期时间）
        foreach ($buckets as $bucket) {
            Cache::add($bucket['key'], 0, $bucket['decay']);
            Cache::increment($bucket['key']);
        }

        return null;
    }

    /**
     * 同内容去重：窗口内相同内容重复提交返回 true（应拦截）。
     */
    private function guardDuplicateContent(Request $request, $userId, string $action): bool
    {
        $content = trim((string) $request->input('content', ''));
        if ($content === '') {
            return false; // 纯媒体内容不做文本去重
        }

        $key = self::CACHE_PREFIX . 'dup:' . $action . ':u' . $userId . ':' . sha1(mb_strtolower($content));

        $window = (int) config('security.duplicate_content_window_seconds', 10);

        return ! Cache::add($key, 1, $window); // add 失败说明窗口内已存在相同内容
    }

    /**
     * 统一 429 响应：与全局 throttle 渲染 / IP 闸门结构一致，
     * 携带 Retry-After / X-RateLimit-* 头，并记录限流事件供后台监控。
     */
    private function tooManyResponse(int $retryAfter, ?int $limit = null, ?string $category = null, ?string $action = null, ?int $userId = null): Response
    {
        if ($category !== null && $userId !== null) {
            record_throttle_event(
                ApiThrottleEvent::DIMENSION_USER,
                (string) $userId,
                $category,
                $action,
                $limit,
                $retryAfter
            );
        }

        $headers = ['Retry-After' => $retryAfter];

        if ($limit !== null) {
            $headers['X-RateLimit-Limit'] = $limit;
            $headers['X-RateLimit-Remaining'] = 0;
            $headers['X-RateLimit-Reset'] = now()->addSeconds($retryAfter)->getTimestamp();
        }

        // 带剩余等待秒数时给用户更明确的提示（与全局 throttle 渲染 / IP 闸门一致）
        $message = ($retryAfter > 0)
            ? __('api/error.throttle_seconds', ['seconds' => $retryAfter])
            : __('api/error.throttle');

        return response()->json([
            'status'  => 'error',
            'code'    => 429,
            'message' => $message,
        ], 429, $headers);
    }
}
