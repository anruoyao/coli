<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Ultimate Social Network Web Application.
|--------------------------------------------------------------------------
| 媒体上传大小限制（移动客户端上传前拉取，用于本地预检测）。
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;

class UploadLimitsController extends Controller
{
    use SupportsApiResponses;

    /**
     * 上传大小限制接口（无需登录）。
     *
     * 响应（colibri 统一封装 { status, code, data }）：
     *   - 每种媒体类型返回 max（KB，与 Laravel max 校验规则单位一致）与 max_mb（MB，便于展示文案）。
     * 客户端在上传前用本地文件字节数与 max * 1024 对比，超出立即提示，避免无谓上传。
     */
    public function limits()
    {
        return $this->responseSuccess([
            'data' => [
                'image' => $this->limit('image'),
                'video' => $this->limit('video'),
                'audio' => $this->limit('audio'),
                'gif' => $this->limit('gif'),
                'document' => $this->limit('document'),
            ],
        ]);
    }

    /**
     * 单个媒体类型的限制信息：max（KB）、max_mb（MB）与统一超限提示文案。
     * 文案由后端语言文件生成，客户端（App / 网页）本地预检测时直接展示，保证两端一致。
     */
    private function limit(string $type): array
    {
        $maxMb = (int) config("upload.{$type}.max_mb");

        return [
            'max' => (int) config("upload.{$type}.max"),
            'max_mb' => $maxMb,
            'message' => __("api/upload.{$type}_exceeded", ['max_mb' => $maxMb]),
        ];
    }
}
