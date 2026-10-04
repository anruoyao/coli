<?php

namespace App\Services\Nsfw;

use RuntimeException;
use Illuminate\Support\Facades\Http;

/**
 * NSFW 检测客户端（对接 nsfw-service 微服务，NudeNet）
 *
 * 微服务只返回原始 detections，判定逻辑（阈值 + 触发标签集）在本类
 * evaluate() 中按 config('features.nsfw_detection.*') 执行，便于后台调参。
 */
class NsfwDetectionService
{
    /**
     * 检测图片（含 GIF，opencv 读首帧）
     *
     * @return array<int, array{label: string, score: float, box: array, frame: int}>
     */
    public function detectImage(string $absPath): array
    {
        return $this->requestDetection($absPath, 'image');
    }

    /**
     * 检测视频（微服务侧 ffmpeg 抽帧后逐帧检测）
     */
    public function detectVideo(string $absPath): array
    {
        return $this->requestDetection($absPath, 'video');
    }

    /**
     * 按阈值 + 触发标签集过滤检测结果，返回命中项
     *
     * @return array<int, array{label: string, score: float}>
     */
    public function evaluate(array $detections): array
    {
        $threshold = (float) config('features.nsfw_detection.threshold', 0.60);
        $triggerLabels = (array) config('features.nsfw_detection.trigger_labels', []);

        $hits = [];
        foreach ($detections as $detection) {
            $label = $detection['label'] ?? null;
            $score = (float) ($detection['score'] ?? 0);

            if ($label !== null && in_array($label, $triggerLabels) && $score >= $threshold) {
                $hits[] = ['label' => $label, 'score' => $score];
            }
        }

        return $hits;
    }

    /**
     * 健康检查（后台连通性测试）
     */
    public function healthCheck(): array
    {
        $response = Http::baseUrl($this->baseUrl())
            ->timeout($this->connectTimeout())
            ->get('/health');

        return $response->successful() ? $response->json() : [];
    }

    private function requestDetection(string $absPath, string $type): array
    {
        if (! is_file($absPath) || ! is_readable($absPath)) {
            throw new RuntimeException("NSFW detection source file not readable: {$absPath}");
        }

        $response = Http::baseUrl($this->baseUrl())
            ->timeout($this->timeout())
            ->connectTimeout($this->connectTimeout())
            ->attach('file', fopen($absPath, 'rb'), basename($absPath))
            ->post('/v1/detect', ['type' => $type]);

        if (! $response->successful()) {
            throw new RuntimeException("NSFW detection service error [{$response->status()}]: {$response->body()}");
        }

        $payload = $response->json();

        if (empty($payload['ok'])) {
            throw new RuntimeException('NSFW detection service returned failure: ' . ($payload['error'] ?? 'unknown'));
        }

        return (array) ($payload['detections'] ?? []);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.nsfw_detection.url'), '/');
    }

    private function timeout(): int
    {
        return (int) config('services.nsfw_detection.timeout', 30);
    }

    private function connectTimeout(): int
    {
        return (int) config('services.nsfw_detection.connect_timeout', 3);
    }
}
