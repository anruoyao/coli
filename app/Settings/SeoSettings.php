<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * SEO / Sitemap 全局设置。
 *
 * 涵盖三块：
 * 1. Sitemap（生成 /sitemap.xml 索引 + 分片，供搜索引擎发现 URL）；
 * 2. SEO 公开页（对未登录访客服务端直出 meta / JSON-LD / 正文快照，解决 SPA 空壳抓取问题）；
 * 3. robots.txt（后台控制注入 Sitemap 行）。
 */
class SeoSettings extends Settings
{
    // ----- 主开关 -----
    public bool $enabled;
    public bool $seo_head_enabled;

    // ----- Sitemap 基础 -----
    public int $per_page;
    public int $cache_ttl;

    // ----- 收录类型开关 -----
    public bool $include_static;
    public bool $include_users;
    public bool $include_posts;
    public bool $include_stories;
    public bool $include_jobs;
    public bool $include_products;

    // ----- 各类内容数量上限（0 = 不限制）-----
    public int $users_limit;
    public int $posts_limit;
    public int $stories_limit;
    public int $jobs_limit;
    public int $products_limit;

    // ----- 各类 changefreq / priority 默认值 -----
    public string $static_changefreq;
    public string $static_priority;
    public string $users_changefreq;
    public string $users_priority;
    public string $posts_changefreq;
    public string $posts_priority;
    public string $stories_changefreq;
    public string $stories_priority;
    public string $jobs_changefreq;
    public string $jobs_priority;
    public string $products_changefreq;
    public string $products_priority;

    // ----- 排除规则（路径前缀，支持 /regex/ 形式）-----
    public array $excluded_paths;

    // ----- robots.txt -----
    public bool $robots_sitemap_line;
    public string $robots_custom;

    // ----- 状态与统计 -----
    public ?string $last_generated_at;
    public array $last_stats;
    public ?string $google_last_pinged_at;
    public ?string $bing_last_pinged_at;

    public static function group(): string
    {
        return 'seo';
    }

    /**
     * 各收录类型的 changefreq 常量（用于校验）。
     */
    public static function changefreqs(): array
    {
        return ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
    }
}