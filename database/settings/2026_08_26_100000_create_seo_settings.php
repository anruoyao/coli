<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        DB::transaction(function () {
            // 主开关
            $this->migrator->add('seo.enabled', true);
            $this->migrator->add('seo.seo_head_enabled', true);

            // Sitemap 基础
            $this->migrator->add('seo.per_page', 1000);
            $this->migrator->add('seo.cache_ttl', 60);

            // 收录类型开关
            $this->migrator->add('seo.include_static', true);
            $this->migrator->add('seo.include_users', true);
            $this->migrator->add('seo.include_posts', true);
            $this->migrator->add('seo.include_stories', true);
            $this->migrator->add('seo.include_jobs', true);
            $this->migrator->add('seo.include_products', true);

            // 数量上限
            $this->migrator->add('seo.users_limit', 5000);
            $this->migrator->add('seo.posts_limit', 5000);
            $this->migrator->add('seo.stories_limit', 1000);
            $this->migrator->add('seo.jobs_limit', 1000);
            $this->migrator->add('seo.products_limit', 1000);

            // changefreq / priority
            $this->migrator->add('seo.static_changefreq', 'monthly');
            $this->migrator->add('seo.static_priority', '0.5');
            $this->migrator->add('seo.users_changefreq', 'weekly');
            $this->migrator->add('seo.users_priority', '0.6');
            $this->migrator->add('seo.posts_changefreq', 'daily');
            $this->migrator->add('seo.posts_priority', '0.7');
            $this->migrator->add('seo.stories_changefreq', 'daily');
            $this->migrator->add('seo.stories_priority', '0.5');
            $this->migrator->add('seo.jobs_changefreq', 'weekly');
            $this->migrator->add('seo.jobs_priority', '0.6');
            $this->migrator->add('seo.products_changefreq', 'weekly');
            $this->migrator->add('seo.products_priority', '0.6');

            // 排除规则（默认排除需登录的私有区块）
            $this->migrator->add('seo.excluded_paths', [
                '/settings', '/messenger', '/bookmarks', '/wallet',
                '/auth', '/onboarding', '/switcher', '/logout',
            ]);

            // robots.txt
            $this->migrator->add('seo.robots_sitemap_line', true);
            $this->migrator->add('seo.robots_custom', '');

            // 状态与统计
            $this->migrator->add('seo.last_generated_at', null);
            $this->migrator->add('seo.last_stats', []);
            $this->migrator->add('seo.google_last_pinged_at', null);
            $this->migrator->add('seo.bing_last_pinged_at', null);
        });
    }
};