<?php

namespace App\Livewire\Admin\Config;

use App\Settings\SeoSettings;
use App\Services\Sitemap\SitemapService;
use App\Support\Views\Flash;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Sitemap / SEO 管理页。
 *
 * 覆盖：总览统计、Sitemap 生成设置、SEO 公开页设置、排除规则、
 * 搜索引擎推送（Google / Bing ping）、robots.txt 注入。
 */
class Sitemap extends Component
{
    public array $formData = [];

    public string $actionMessage = '';

    public function mount()
    {
        $settings = app(SeoSettings::class);

        $this->formData = [
            // 主开关
            'enabled' => $settings->enabled,
            'seo_head_enabled' => $settings->seo_head_enabled,
            // Sitemap 基础
            'per_page' => $settings->per_page,
            'cache_ttl' => $settings->cache_ttl,
            // 收录开关
            'include_static' => $settings->include_static,
            'include_users' => $settings->include_users,
            'include_posts' => $settings->include_posts,
            'include_stories' => $settings->include_stories,
            'include_jobs' => $settings->include_jobs,
            'include_products' => $settings->include_products,
            // 数量上限
            'users_limit' => $settings->users_limit,
            'posts_limit' => $settings->posts_limit,
            'stories_limit' => $settings->stories_limit,
            'jobs_limit' => $settings->jobs_limit,
            'products_limit' => $settings->products_limit,
            // changefreq / priority
            'static_changefreq' => $settings->static_changefreq,
            'static_priority' => $settings->static_priority,
            'users_changefreq' => $settings->users_changefreq,
            'users_priority' => $settings->users_priority,
            'posts_changefreq' => $settings->posts_changefreq,
            'posts_priority' => $settings->posts_priority,
            'stories_changefreq' => $settings->stories_changefreq,
            'stories_priority' => $settings->stories_priority,
            'jobs_changefreq' => $settings->jobs_changefreq,
            'jobs_priority' => $settings->jobs_priority,
            'products_changefreq' => $settings->products_changefreq,
            'products_priority' => $settings->products_priority,
            // 排除规则（每行一条）
            'excluded_paths' => implode("\n", $settings->excluded_paths ?: []),
            // robots.txt
            'robots_sitemap_line' => $settings->robots_sitemap_line,
            'robots_custom' => $settings->robots_custom,
        ];
    }

    public function submitForm()
    {
        $this->validate($this->rules(), attributes: $this->attributes());

        $settings = app(SeoSettings::class);

        $settings->enabled = (bool) $this->formData['enabled'];
        $settings->seo_head_enabled = (bool) $this->formData['seo_head_enabled'];
        $settings->per_page = (int) $this->formData['per_page'];
        $settings->cache_ttl = (int) $this->formData['cache_ttl'];

        $settings->include_static = (bool) $this->formData['include_static'];
        $settings->include_users = (bool) $this->formData['include_users'];
        $settings->include_posts = (bool) $this->formData['include_posts'];
        $settings->include_stories = (bool) $this->formData['include_stories'];
        $settings->include_jobs = (bool) $this->formData['include_jobs'];
        $settings->include_products = (bool) $this->formData['include_products'];

        $settings->users_limit = (int) $this->formData['users_limit'];
        $settings->posts_limit = (int) $this->formData['posts_limit'];
        $settings->stories_limit = (int) $this->formData['stories_limit'];
        $settings->jobs_limit = (int) $this->formData['jobs_limit'];
        $settings->products_limit = (int) $this->formData['products_limit'];

        $settings->static_changefreq = $this->formData['static_changefreq'];
        $settings->static_priority = $this->formData['static_priority'];
        $settings->users_changefreq = $this->formData['users_changefreq'];
        $settings->users_priority = $this->formData['users_priority'];
        $settings->posts_changefreq = $this->formData['posts_changefreq'];
        $settings->posts_priority = $this->formData['posts_priority'];
        $settings->stories_changefreq = $this->formData['stories_changefreq'];
        $settings->stories_priority = $this->formData['stories_priority'];
        $settings->jobs_changefreq = $this->formData['jobs_changefreq'];
        $settings->jobs_priority = $this->formData['jobs_priority'];
        $settings->products_changefreq = $this->formData['products_changefreq'];
        $settings->products_priority = $this->formData['products_priority'];

        $settings->excluded_paths = $this->parseLines($this->formData['excluded_paths']);
        $settings->robots_sitemap_line = (bool) $this->formData['robots_sitemap_line'];
        $settings->robots_custom = trim((string) ($this->formData['robots_custom'] ?? ''));

        $settings->save();

        // 保存后立即重建 sitemap 缓存并写回 robots.txt
        $service = app(SitemapService::class);
        $service->warm();
        $service->applyRobots();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/sitemap.flash.saved')))->get())
            ->route('admin.config.sitemap');
    }

    public function regenerate()
    {
        $stats = app(SitemapService::class)->warm();

        $this->actionMessage = __('admin/sitemap.flash.regenerated', [
            'count' => (int) array_sum($stats),
        ]);
    }

    public function pingGoogle()
    {
        $this->ping('google');
    }

    public function pingBing()
    {
        $this->ping('bing');
    }

    protected function ping(string $engine)
    {
        $success = app(SitemapService::class)->ping($engine);

        $this->actionMessage = $success
            ? __('admin/sitemap.flash.ping_success', ['engine' => __("admin/sitemap.engines.{$engine}")])
            : __('admin/sitemap.flash.ping_failed', ['engine' => __("admin/sitemap.engines.{$engine}")]);
    }

    public function render()
    {
        $service = app(SitemapService::class);
        $settings = app(SeoSettings::class);

        return view('livewire.admin.config.sitemap', [
            'stats' => $service->statistics() + [
                'settings_google_pinged' => $this->formatPingedAt($settings->google_last_pinged_at),
                'settings_bing_pinged' => $this->formatPingedAt($settings->bing_last_pinged_at),
            ],
            'robotsContent' => $service->currentRobotsContent(),
            'changefreqOptions' => collect(SeoSettings::changefreqs())->map(fn ($value) => [
                'key' => $value,
                'value' => $value,
            ])->all(),
            'typeRows' => [
                $this->typeRow('users', __('admin/sitemap.types.users'), 'include_users', 'users_limit', 'users_changefreq', 'users_priority'),
                $this->typeRow('posts', __('admin/sitemap.types.posts'), 'include_posts', 'posts_limit', 'posts_changefreq', 'posts_priority'),
                $this->typeRow('stories', __('admin/sitemap.types.stories'), 'include_stories', 'stories_limit', 'stories_changefreq', 'stories_priority'),
                $this->typeRow('jobs', __('admin/sitemap.types.jobs'), 'include_jobs', 'jobs_limit', 'jobs_changefreq', 'jobs_priority'),
                $this->typeRow('products', __('admin/sitemap.types.products'), 'include_products', 'products_limit', 'products_changefreq', 'products_priority'),
            ],
        ]);
    }

    protected function typeRow(string $type, string $label, string $includeKey, string $limitKey, string $changefreqKey, string $priorityKey): array
    {
        return [
            'type' => $type,
            'label' => $label,
            'include_key' => $includeKey,
            'limit' => $this->formData[$limitKey] ?? 0,
            'limit_key' => $limitKey,
            'changefreq' => $this->formData[$changefreqKey] ?? 'weekly',
            'changefreq_key' => $changefreqKey,
            'priority' => $this->formData[$priorityKey] ?? '0.5',
            'priority_key' => $priorityKey,
        ];
    }

    protected function formatPingedAt(?string $value): string
    {
        return $value
            ? \Illuminate\Support\Carbon::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i')
            : __('admin/sitemap.overview.never');
    }

    protected function rules(): array
    {
        return [
            'formData.enabled' => ['boolean'],
            'formData.seo_head_enabled' => ['boolean'],
            'formData.per_page' => ['required', 'integer', 'min:100', 'max:50000'],
            'formData.cache_ttl' => ['required', 'integer', 'min:5', 'max:1440'],
            'formData.include_static' => ['boolean'],
            'formData.include_users' => ['boolean'],
            'formData.include_posts' => ['boolean'],
            'formData.include_stories' => ['boolean'],
            'formData.include_jobs' => ['boolean'],
            'formData.include_products' => ['boolean'],
            'formData.users_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'formData.posts_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'formData.stories_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'formData.jobs_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'formData.products_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'formData.static_changefreq' => ['required', Rule::in(SeoSettings::changefreqs())],
            'formData.static_priority' => ['required', 'regex:/^(0(\.[0-9])?|1(\.0)?)$/'],
            'formData.users_changefreq' => ['required', Rule::in(SeoSettings::changefreqs())],
            'formData.users_priority' => ['required', 'regex:/^(0(\.[0-9])?|1(\.0)?)$/'],
            'formData.posts_changefreq' => ['required', Rule::in(SeoSettings::changefreqs())],
            'formData.posts_priority' => ['required', 'regex:/^(0(\.[0-9])?|1(\.0)?)$/'],
            'formData.stories_changefreq' => ['required', Rule::in(SeoSettings::changefreqs())],
            'formData.stories_priority' => ['required', 'regex:/^(0(\.[0-9])?|1(\.0)?)$/'],
            'formData.jobs_changefreq' => ['required', Rule::in(SeoSettings::changefreqs())],
            'formData.jobs_priority' => ['required', 'regex:/^(0(\.[0-9])?|1(\.0)?)$/'],
            'formData.products_changefreq' => ['required', Rule::in(SeoSettings::changefreqs())],
            'formData.products_priority' => ['required', 'regex:/^(0(\.[0-9])?|1(\.0)?)$/'],
            'formData.excluded_paths' => ['nullable', 'string', 'max:2000'],
            'formData.robots_sitemap_line' => ['boolean'],
            'formData.robots_custom' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function attributes(): array
    {
        return [
            'formData.enabled' => __('admin/sitemap.form.enabled'),
            'formData.seo_head_enabled' => __('admin/sitemap.form.seo_head_enabled'),
            'formData.per_page' => __('admin/sitemap.form.per_page'),
            'formData.cache_ttl' => __('admin/sitemap.form.cache_ttl'),
            'formData.excluded_paths' => __('admin/sitemap.form.excluded_paths'),
            'formData.robots_custom' => __('admin/sitemap.form.robots_custom'),
        ];
    }

    protected function parseLines(?string $text): array
    {
        return collect(explode("\n", (string) $text))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();
    }
}