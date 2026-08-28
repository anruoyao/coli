<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * API 滥用防护中间件（P0 防刷数据）。
 *
 * 两层防护（配置见 config/security.php）：
 * 1. 按「用户 × 动作 × 时间窗」计数限流；新账号（注册 < new_user_window_hours）
 *    执行更严格的新人限额。
 * 2. 同内容幂等去重：同一用户在同一窗口内对同一动作重复提交相同内容直接拒绝，
 *    拦截脚本连刷帖子 / 评论 / 私信。
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

        // 管理员 / 审核角色豁免
        if (in_array((string) $user->role, ['root', 'admin', 'moderator'], true)) {
            return $next($request);
        }

        foreach (config('security.actions', []) as $action => $rule) {
            if (! $this->matches($request, $rule)) {
                continue;
            }

            if (in_array($action, self::DUP_CONTENT_ACTIONS, true)) {
                $duplicate = $this->guardDuplicateContent($request, $user->id, $action);
                if ($duplicate) {
                    return $this->tooManyResponse((int) config('security.duplicate_content_window_seconds', 10));
                }
            }

            if (! $this->guardRateLimit($user->id, $user->created_at, $action, $rule)) {
                return $this->tooManyResponse($rule['decay'] ?? 60);
            }
        }

        return $next($request);
    }

    /**
     * 判断请求是否命中某动作规则（路径前缀/精确匹配 + 可选方法过滤）。
     */
    private function matches(Request $request, array $rule): bool
    {
        $path = $request->path(); // 如 api/post/editor/create

        $matched = false;
        foreach ($rule['paths'] ?? [] as $pathRule) {
            $pathRule = trim($pathRule, '/');
            if ($path === $pathRule || str_starts_with($path, $pathRule)) {
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
     * 按用户 × 动作 × 时间窗计数，返回是否放行。
     */
    private function guardRateLimit($userId, $createdAt, string $action, array $rule): bool
    {
        $isNewUser = $createdAt && $createdAt->gt(now()->subHours((int) config('security.new_user_window_hours', 24)));

        $max   = ($isNewUser && isset($rule['new_user_max'])) ? $rule['new_user_max'] : $rule['max'];
        $decay = ($isNewUser && isset($rule['new_user_decay'])) ? $rule['new_user_decay'] : $rule['decay'];

        $key = self::CACHE_PREFIX . $action . ':u' . $userId;

        $count = (int) Cache::get($key, 0);
        if ($count >= (int) $max) {
            return false;
        }

        // 确保 key 存在并带 TTL（已存在时 add 为空操作，不重置过期时间）
        Cache::add($key, 0, (int) $decay);
        Cache::increment($key);

        return true;
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

    private function tooManyResponse(int $retryAfter): Response
    {
        return response()->json([
            'status'  => 'error',
            'code'    => 429,
            'message' => 'Too many attempts. Please slow down and try again later.',
        ], 429, ['Retry-After' => $retryAfter]);
    }
}