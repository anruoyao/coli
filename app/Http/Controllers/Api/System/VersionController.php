<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Ultimate Social Network Web Application.
|--------------------------------------------------------------------------
| App 版本检测（移动客户端启动时调用）。
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\System;

use App\Models\AppVersion;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class VersionController extends Controller
{
    use SupportsApiResponses;

    /**
     * 版本检测接口（无需登录）。
     *
     * 请求参数：
     *   - code:     客户端当前版本代号（语义化版本号，如 1.2.3）
     *   - platform: 客户端平台（android / ios）
     *
     * 响应（colibri 统一封装 { status, code, data }）：
     *   - has_update: 是否存在可用的新版本
     *   - forced:     新版本是否强制更新（has_update 为 true 时才有意义）
     *   - latest:     最新上线的版本信息（版本代号 / 下载链接 / 更新公告 / 发布时间）
     */
    public function check(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[0-9]+(\.[0-9]+){1,3}$/'],
            'platform' => ['required', 'in:android,ios'],
        ]);

        $currentCode = $validated['code'];
        $platform = $validated['platform'];

        $versions = AppVersion::query()
            ->active()
            ->where('platform', $platform)
            ->get();

        // 按语义化版本号取最高的最新版本（避免字符串排序导致 2.10.0 < 2.9.0 之类的问题）。
        $latest = $versions->reduce(function (?AppVersion $carry, AppVersion $version) {
            if ($carry === null || version_compare($version->code, $carry->code, '>')) {
                return $version;
            }
            return $carry;
        });

        if ($latest === null) {
            return $this->responseSuccess([
                'data' => [
                    'has_update' => false,
                    'forced' => false,
                    'latest' => null,
                ],
            ]);
        }

        $hasUpdate = version_compare($latest->code, $currentCode, '>');

        return $this->responseSuccess([
            'data' => [
                'has_update' => $hasUpdate,
                'forced' => $hasUpdate && $latest->is_forced,
                'latest' => [
                    'code' => $latest->code,
                    'platform' => $latest->platform,
                    'download_url' => $latest->download_url,
                    'notes' => $latest->notes,
                    'is_forced' => $latest->is_forced,
                    'released_at' => $latest->released_at?->toIso8601String(),
                ],
            ],
        ]);
    }
}