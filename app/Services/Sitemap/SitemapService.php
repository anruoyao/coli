<?php

namespace App\Services\Sitemap;

use App\Enums\Media\MediaType;
use App\Models\JobListing;
use App\Models\Post;
use App\Models\Product;
use App\Models\Story;
use App\Models\User;
use App\Settings\SeoSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 企业级 Sitemap 生成器。
 *
 * 输出结构：
 *   /sitemap.xml                      —— sitemap 索引
 *   /sitemap-{type}-{page}.xml        —— 分片（默认每片 1000 条，可配置）
 *
 * 特性：按内容类型分片、lastmod/changefreq/priority、帖子图片扩展（image sitemap）、
 * 排除规则（含正则）、缓存（TTL 可配）、主动重生成与统计、robots.txt 注入、搜索引擎 ping。
 */
class SitemapService
{
    public const CACHE_INDEX = 'seo.sitemap.index';
    public const CACHE_CHUNK = 'seo.sitemap.chunk.';
    public const CACHE_STATS = 'seo.sitemap.stats';

    /** 分片文件顺序（也是 sitemap 索引中的展示顺序）。 */
    public const TYPES = ['static', 'users', 'posts', 'stories', 'jobs', 'products'];

    public function __construct(protected SeoSettings $settings)
    {
    }

    public function enabled(): bool
    {
        return $this->settings->enabled;
    }

    public function perPage(): int
    {
        return max(1, (int) $this->settings->per_page);
    }

    public function ttlSeconds(): int
    {
        return max(60, (int) $this->settings->cache_ttl) * 60;
    }

    public function indexUrl(): string
    {
        return url('sitemap.xml');
    }

    public function chunkUrl(string $type, int $page): string
    {
        return url("sitemap-{$type}-{$page}.xml");
    }

    public function robotsFilePath(): string
    {
        return public_path('robots.txt');
    }

    /**
     * 生成（或重建）全部缓存：索引 + 各类型分片 + 统计，并记录生成时间。
     */
    public function warm(): array
    {
        $stats = [];
        $previous = $this->settings->last_stats ?: [];

        foreach (self::TYPES as $type) {
            $priorPages = (int) ceil((int) ($previous[$type] ?? 0) / $this->perPage());

            if (! $this->shouldInclude($type)) {
                $stats[$type] = 0;
                $this->forgetChunkPages($type, 1, $priorPages);
                continue;
            }

            $entries = $this->collectUrls($type);
            $stats[$type] = $entries->count();
            $pages = $entries->chunk($this->perPage())->values();

            foreach ($pages as $index => $page) {
                Cache::put(self::CACHE_CHUNK.$type.'.'.($index + 1), $this->renderChunk($page), $this->ttlSeconds());
            }

            // 清理超出新分片数的旧缓存，避免索引外的分片仍可被直接访问
            $this->forgetChunkPages($type, $pages->count() + 1, $priorPages);
        }

        Cache::put(self::CACHE_INDEX, $this->renderIndex($stats), $this->ttlSeconds());
        Cache::put(self::CACHE_STATS, $stats, $this->ttlSeconds());

        $this->settings->last_generated_at = now()->toIso8601String();
        $this->settings->last_stats = $stats;
        $this->settings->save();

        return $stats;
    }

    public function clear(): void
    {
        foreach (self::TYPES as $type) {
            $pages = (int) ceil((int) ($this->settings->last_stats[$type] ?? 0) / $this->perPage());
            $this->forgetChunkPages($type, 1, $pages);
        }

        Cache::forget(self::CACHE_INDEX);
        Cache::forget(self::CACHE_STATS);
    }

    protected function forgetChunkPages(string $type, int $from, int $to): void
    {
        for ($page = $from; $page <= $to; $page++) {
            Cache::forget(self::CACHE_CHUNK.$type.'.'.$page);
        }
    }

    public function getIndexContent(): ?string
    {
        if (! $this->enabled() || ! Cache::has(self::CACHE_INDEX)) {
            return null;
        }

        return Cache::get(self::CACHE_INDEX);
    }

    public function getChunkContent(string $type, int $page): ?string
    {
        if (! $this->enabled() || ! in_array($type, self::TYPES) || $page < 1) {
            return null;
        }

        $key = self::CACHE_CHUNK.$type.'.'.$page;

        return Cache::has($key) ? Cache::get($key) : null;
    }

    public function statistics(): array
    {
        $stats = Cache::get(self::CACHE_STATS, $this->settings->last_stats ?: []);

        $labels = $this->typeLabels();

        return [
            'enabled' => $this->enabled(),
            'per_page' => $this->perPage(),
            'cache_ttl' => (int) $this->settings->cache_ttl,
            'index' => $this->indexUrl(),
            'last_generated_at' => $this->settings->last_generated_at,
            'per_type' => $stats,
            'type_labels' => $labels,
            'total' => array_sum($stats),
            'chunks' => collect($stats)->map(fn ($count, $type) => (int) ceil($count / $this->perPage()))->toArray(),
        ];
    }

    /**
     * 按类型收集 URL 条目集合。
     *
     * 每个条目：['loc', 'lastmod'(?string), 'changefreq', 'priority', 'images'(array)]
     */
    protected function collectUrls(string $type): Collection
    {
        $entries = match ($type) {
            'static' => $this->staticUrls(),
            'users' => $this->userUrls(),
            'posts' => $this->postUrls(),
            'stories' => $this->storyUrls(),
            'jobs' => $this->jobUrls(),
            'products' => $this->productUrls(),
            default => collect(),
        };

        return $entries->filter(fn (array $entry) => ! $this->isPathExcluded($entry['loc']))
            ->values();
    }

    protected function shouldInclude(string $type): bool
    {
        return (bool) match ($type) {
            'static' => $this->settings->include_static,
            'users' => $this->settings->include_users,
            'posts' => $this->settings->include_posts,
            'stories' => $this->settings->include_stories,
            'jobs' => $this->settings->include_jobs,
            'products' => $this->settings->include_products,
            default => false,
        };
    }

    protected function staticUrls(): Collection
    {
        // 无需登录、可被搜索引擎直接抓取的静态页（MPA 文档页）
        $pages = [
            'document.about.index',
            'document.help.index',
            'document.terms.index',
            'document.privacy.index',
            'document.cookies.index',
            'document.developers.index',
            'document.verification.index',
            'document.author.index',
        ];

        return collect($pages)->filter(fn (string $name) => \Illuminate\Support\Facades\Route::has($name))->map(function (string $name) {
            return $this->entry(
                loc: route($name),
                lastmod: null,
                changefreq: $this->settings->static_changefreq,
                priority: $this->settings->static_priority,
            );
        });
    }

    protected function userUrls(): Collection
    {
        $limit = (int) $this->settings->users_limit;

        return User::query()
            ->active()
            ->select(['id', 'username', 'updated_at'])
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->orderByDesc('id')
            ->get()
            ->map(fn (User $user) => $this->entry(
                loc: url("@{$user->username}"),
                lastmod: $user->updated_at,
                changefreq: $this->settings->users_changefreq,
                priority: $this->settings->users_priority,
            ));
    }

    protected function postUrls(): Collection
    {
        $limit = (int) $this->settings->posts_limit;

        return Post::query()
            ->active()
            ->where('is_sensitive', false)
            ->whereHas('user', fn ($query) => $query->active())
            ->with(['media' => fn ($query) => $query->where('type', MediaType::IMAGE)])
            ->select(['id', 'updated_at'])
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Post $post) => $this->entry(
                loc: $post->url,
                lastmod: $post->updated_at,
                changefreq: $this->settings->posts_changefreq,
                priority: $this->settings->posts_priority,
                images: $post->media->map(fn ($media) => [
                    'loc' => $media->source_url,
                    'title' => '',
                ])->all(),
            ));
    }

    protected function storyUrls(): Collection
    {
        $limit = (int) $this->settings->stories_limit;

        return Story::query()
            ->active()
            ->whereHas('user', fn ($query) => $query->active())
            ->select(['story_uuid', 'updated_at'])
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Story $story) => $this->entry(
                loc: $story->url,
                lastmod: $story->updated_at,
                changefreq: $this->settings->stories_changefreq,
                priority: $this->settings->stories_priority,
            ));
    }

    protected function jobUrls(): Collection
    {
        $limit = (int) $this->settings->jobs_limit;

        return JobListing::query()
            ->listable()
            ->whereHas('user', fn ($query) => $query->active())
            ->select(['id', 'updated_at'])
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->orderByDesc('id')
            ->get()
            ->map(fn (JobListing $job) => $this->entry(
                loc: $job->url,
                lastmod: $job->updated_at,
                changefreq: $this->settings->jobs_changefreq,
                priority: $this->settings->jobs_priority,
            ));
    }

    protected function productUrls(): Collection
    {
        $limit = (int) $this->settings->products_limit;

        return Product::query()
            ->listable()
            ->whereHas('user', fn ($query) => $query->active())
            ->select(['id', 'updated_at'])
            ->when($limit > 0, fn ($query) => $query->limit($limit))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Product $product) => $this->entry(
                loc: $product->url,
                lastmod: $product->updated_at,
                changefreq: $this->settings->products_changefreq,
                priority: $this->settings->products_priority,
            ));
    }

    protected function entry(string $loc, $lastmod, string $changefreq, string $priority, array $images = []): array
    {
        return [
            'loc' => $loc,
            'lastmod' => $lastmod ? $this->toW3c($lastmod) : null,
            'changefreq' => $changefreq ?: 'weekly',
            'priority' => $priority ?: '0.5',
            'images' => collect($images)->map(fn (array $image) => [
                'loc' => $image['loc'],
                'title' => $image['title'] ?? '',
            ])->filter(fn (array $image) => ! empty($image['loc']))->values()->all(),
        ];
    }

    protected function renderIndex(array $stats): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach (self::TYPES as $type) {
            $pages = (int) ceil((int) ($stats[$type] ?? 0) / $this->perPage());
            for ($page = 1; $page <= $pages; $page++) {
                $xml .= "  <sitemap>\n";
                $xml .= '    <loc>'.$this->escape($this->chunkUrl($type, $page))."</loc>\n";
                $xml .= "  </sitemap>\n";
            }
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    protected function renderChunk(Collection $entries): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.$this->escape($entry['loc'])."</loc>\n";
            if ($entry['lastmod']) {
                $xml .= '    <lastmod>'.$this->escape($entry['lastmod'])."</lastmod>\n";
            }
            $xml .= '    <changefreq>'.$this->escape($entry['changefreq'])."</changefreq>\n";
            $xml .= '    <priority>'.$this->escape($entry['priority'])."</priority>\n";

            foreach ((array) ($entry['images'] ?? []) as $image) {
                $xml .= "    <image:image>\n";
                $xml .= '      <image:loc>'.$this->escape($image['loc'])."</image:loc>\n";
                if (! empty($image['title'])) {
                    $xml .= '      <image:title>'.$this->escape($image['title'])."</image:title>\n";
                }
                $xml .= "    </image:image>\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    protected function isPathExcluded(string $loc): bool
    {
        $path = parse_url($loc, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/').'/'; // 统一起止斜杠便于前缀匹配

        foreach ((array) $this->settings->excluded_paths as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }

            // 支持 /正则/ 形式的高级排除；否则按路径前缀匹配
            if (preg_match('#^/.+/[imsxuADSUXJ]*$#', $pattern)) {
                $delimiter = substr($pattern, 0, 1);
                $flagsEnd = strrpos($pattern, $delimiter);
                $flags = substr($pattern, $flagsEnd + 1);
                $body = substr($pattern, 1, $flagsEnd - 1);

                if (@preg_match($delimiter.$body.$delimiter.$flags, $loc)) {
                    return true;
                }
            } elseif (str_starts_with($path, rtrim($pattern, '/').'/')) {
                return true;
            }
        }

        return false;
    }

    protected function toW3c($date): string
    {
        return \Illuminate\Support\Carbon::parse($date)->toW3cString();
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * robots.txt：按设置写入 public/robots.txt。
     */
    public function applyRobots(): void
    {
        $content = trim($this->settings->robots_custom);

        if ($content === '') {
            $content = "User-agent: *\nDisallow:";
        }

        if ($this->settings->robots_sitemap_line && $this->enabled()) {
            $content .= "\n\nSitemap: ".$this->indexUrl();
        }

        $content .= "\n";

        file_put_contents($this->robotsFilePath(), $content);
    }

    public function currentRobotsContent(): string
    {
        $path = $this->robotsFilePath();

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * IndexNow 密钥（32 位十六进制）。首次使用自动生成并持久化，
     * 搜索引擎推送时会回源 GET /{key}.txt 校验域名所有权。
     */
    public function indexNowKey(): string
    {
        $key = trim((string) $this->settings->indexnow_key);

        if ($key === '') {
            $key = bin2hex(random_bytes(16));

            $this->settings->indexnow_key = $key;
            $this->settings->save();
        }

        return $key;
    }

    public function indexNowKeyLocation(): string
    {
        return url('/'.$this->indexNowKey().'.txt');
    }

    public function indexNowKeyFileContent(): string
    {
        return $this->indexNowKey();
    }

    /**
     * 通过 IndexNow 主动推送 sitemap 收录的 URL（Bing / Yandex / Seznam 等支持）。
     *
     * 背景：Google 于 2023-06 关闭无认证 sitemap ping（google.com/ping 返回 404），
     * Bing 的 ping 接口也已下线（410 Gone）。IndexNow 是当前唯一免注册的主动推送通道。
     *
     * 返回 ['ok' => bool, 'status' => int, 'submitted' => int]
     * 200 = 已接收；202 = 已接收、待密钥文件校验；其余为失败。
     */
    public function pushUrlsToIndexNow(int $max = 10000): array
    {
        if (! $this->enabled()) {
            return ['ok' => false, 'status' => 0, 'submitted' => 0];
        }

        // 确保 sitemap 缓存存在（与公开端点一致的懒生成策略）
        if (! Cache::has(self::CACHE_INDEX)) {
            $this->warm();
        }

        $urls = $this->collectAllUrls($max);

        if (empty($urls)) {
            return ['ok' => false, 'status' => 0, 'submitted' => 0];
        }

        $payload = [
            'host' => (string) parse_url(url('/'), PHP_URL_HOST),
            'key' => $this->indexNowKey(),
            'keyLocation' => $this->indexNowKeyLocation(),
            'urlList' => $urls,
        ];

        // 优先通用入口，失败时回退 Bing 直连入口
        $endpoints = [
            'https://api.indexnow.org/indexnow',
            'https://www.bing.com/indexnow',
        ];

        $lastStatus = 0;

        foreach ($endpoints as $endpoint) {
            try {
                $response = (new \GuzzleHttp\Client(['timeout' => 20]))->post($endpoint, [
                    'json' => $payload,
                    'http_errors' => false,
                ]);

                $lastStatus = $response->getStatusCode();

                if (in_array($lastStatus, [200, 202], true)) {
                    $this->settings->bing_last_pinged_at = now()->toIso8601String();
                    $this->settings->save();

                    return ['ok' => true, 'status' => $lastStatus, 'submitted' => count($urls)];
                }
            } catch (\Throwable $th) {
                $lastStatus = 0;
            }
        }

        return ['ok' => false, 'status' => $lastStatus, 'submitted' => count($urls)];
    }

    /**
     * 汇总所有已启用类型的收录 URL（去重、限量），供 IndexNow 推送。
     */
    protected function collectAllUrls(int $max): array
    {
        $urls = [];

        foreach (self::TYPES as $type) {
            if (! $this->shouldInclude($type)) {
                continue;
            }

            foreach ($this->collectUrls($type) as $entry) {
                $urls[] = $entry['loc'];

                if (count($urls) >= $max) {
                    break 2;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    public function typeLabels(): array
    {
        return [
            'static' => __('admin/sitemap.types.static'),
            'users' => __('admin/sitemap.types.users'),
            'posts' => __('admin/sitemap.types.posts'),
            'stories' => __('admin/sitemap.types.stories'),
            'jobs' => __('admin/sitemap.types.jobs'),
            'products' => __('admin/sitemap.types.products'),
        ];
    }
}