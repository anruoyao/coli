<?php

namespace App\Http\Controllers;

use App\Services\Sitemap\SitemapService;

/**
 * 公开 Sitemap 端点。
 *
 * GET /sitemap.xml                    → sitemap 索引
 * GET /sitemap-{type}-{page}.xml      → 内容分片
 *
 * 缓存失效或缺失时自动重建（warm）。
 */
class SitemapController extends Controller
{
    public function __construct(protected SitemapService $service)
    {
    }

    public function index()
    {
        if (! $this->service->enabled()) {
            abort(404);
        }

        if (! \Illuminate\Support\Facades\Cache::has(SitemapService::CACHE_INDEX)) {
            $this->service->warm();
        }

        $content = $this->service->getIndexContent();

        if ($content === null) {
            abort(404);
        }

        return $this->xmlResponse($content);
    }

    public function chunk(string $file)
    {
        if (! preg_match('#^([a-z]+)-([0-9]+)\.xml$#', $file, $matches)) {
            abort(404);
        }

        [$type, $page] = [$matches[1], (int) $matches[2]];

        if (! in_array($type, SitemapService::TYPES)) {
            abort(404);
        }

        if (! $this->service->enabled()) {
            abort(404);
        }

        if (! \Illuminate\Support\Facades\Cache::has(SitemapService::CACHE_CHUNK.$type.'.'.$page)) {
            $this->service->warm();
        }

        $content = $this->service->getChunkContent($type, $page);

        if ($content === null) {
            abort(404);
        }

        return $this->xmlResponse($content);
    }

    protected function xmlResponse(string $content)
    {
        return response($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}