<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRequestMiddleware
{
    /** 明文写入日志会泄露凭据/密钥的请求头 */
    private const SENSITIVE_HEADERS = [
        'authorization', 'cookie', 'set-cookie', 'x-xsrf-token',
        'php-auth-user', 'php-auth-pw', 'proxy-authorization', 'x-app-key',
    ];

    /** 明文写入日志会泄露凭据的输入字段 */
    private const SENSITIVE_INPUT_KEYS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'plain_text_token', 'api_key', 'apikey', 'secret',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if(config('logging.log_requests')) {
            requests_log('Incoming request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->headers->get('referer'),
                'input' => $this->sanitizeInput($request->all()),
                'headers' => $this->sanitizeHeaders($request->headers->all()),
                'cookies' => '[redacted]',
                'session' => '[redacted]',
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
                'created_at' => now()->toDateTimeString(),
            ]);
        }

        return $next($request);
    }

    /**
     * 剔除携带凭据的头，避免 token / cookie 落日志。
     */
    private function sanitizeHeaders(array $headers): array
    {
        foreach (array_keys($headers) as $name) {
            if (in_array(strtolower($name), self::SENSITIVE_HEADERS, true)) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }

    /**
     * 递归脱敏敏感字段（password / token / secret 等）。
     */
    private function sanitizeInput(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeInput($value);
            } elseif (in_array(strtolower((string) $key), self::SENSITIVE_INPUT_KEYS, true) && $value !== '') {
                $data[$key] = '[redacted]';
            }
        }

        return $data;
    }
}
