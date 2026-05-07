<?php

namespace App\Actions\Seo;

use Carbon\Carbon;
use App\Support\DateFormatter;
use Illuminate\Support\Facades\Cache;

class GenerateSitemapAction
{
    private string $type;

    private int $page;

    public function __construct(
        string $type = 'index',
        int $page = 1,
    ) {
        $this->type = $type;
        $this->page = max(1, $page);
    }

    public function execute(): string
    {
        $cacheKey = $this->cacheKey();

        if (config('seo.sitemap.cache.enabled')) {
            return Cache::remember(
                key: $cacheKey,
                ttl: config('seo.sitemap.cache.ttl'),
                callback: fn() => $this->generate(),
            );
        }

        return $this->generate();
    }

    public function clearCache(): void
    {
        if ($this->type === 'index') {
            Cache::forget($this->cacheKey());

            foreach (array_keys(config('seo.sitemap.types')) as $typeName) {
                $typeConfig = config("seo.sitemap.types.{$typeName}");

                if (! ($typeConfig['enabled'] ?? false)) {
                    continue;
                }

                if ($this->isFeatureDisabled($typeConfig)) {
                    continue;
                }

                $totalPages = $this->totalPagesForType(typeName: $typeName, typeConfig: $typeConfig);

                for ($p = 1; $p <= $totalPages; $p++) {
                    Cache::forget("sitemap:{$typeName}:{$p}");
                }
            }

            return;
        }

        Cache::forget($this->cacheKey());
    }

    private function generate(): string
    {
        if ($this->type === 'index') {
            return $this->generateIndex();
        }

        return $this->generateTypeSitemap();
    }

    private function generateIndex(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach (config('seo.sitemap.types') as $typeName => $typeConfig) {
            if (! ($typeConfig['enabled'] ?? false)) {
                continue;
            }

            if ($this->isFeatureDisabled($typeConfig)) {
                continue;
            }

            $totalPages = $this->totalPagesForType(typeName: $typeName, typeConfig: $typeConfig);

            for ($page = 1; $page <= $totalPages; $page++) {
                $loc = url("/sitemap-{$typeName}-{$page}.xml");

                $xml .= "  <sitemap>\n";
                $xml .= "    <loc>{$this->escape($loc)}</loc>\n";
                $xml .= "  </sitemap>\n";
            }
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    private function generateTypeSitemap(): string
    {
        $types = config('seo.sitemap.types');

        if (! isset($types[$this->type])) {
            return $this->emptySitemap();
        }

        $typeConfig = $types[$this->type];

        if (! ($typeConfig['enabled'] ?? false)) {
            return $this->emptySitemap();
        }

        if ($this->isFeatureDisabled($typeConfig)) {
            return $this->emptySitemap();
        }

        if (isset($typeConfig['urls'])) {
            return $this->generateStaticSitemap(config: $typeConfig);
        }

        return $this->generateModelSitemap(typeName: $this->type, config: $typeConfig);
    }

    private function generateStaticSitemap(array $config): string
    {
        $xml = $this->sitemapHeader();

        foreach ($config['urls'] as $url) {
            $xml .= $this->urlEntry(
                loc: url($url['loc']),
                changefreq: $url['changefreq'] ?? 'monthly',
                priority: $url['priority'] ?? '0.5',
                lastmod: now()->toAtomString(),
            );
        }

        $xml .= $this->sitemapFooter();

        return $xml;
    }

    private function generateModelSitemap(string $typeName, array $config): string
    {
        $modelClass = $config['model'];
        $scope = $config['scope'] ?? null;
        $urlCallback = $config['url_callback'];
        $changefreq = $config['changefreq'] ?? 'monthly';
        $priority = $config['priority'] ?? '0.5';
        $lastmodColumn = $config['lastmod_column'] ?? 'updated_at';

        $perPage = config('seo.sitemap.items_per_page', 45000);
        $offset = ($this->page - 1) * $perPage;

        $query = $modelClass::query();

        if ($scope && method_exists($modelClass, 'scope' . ucfirst($scope))) {
            $query->{$scope}();
        }

        $totalItems = min($query->count(), PHP_INT_MAX);
        $totalPages = (int) ceil($totalItems / $perPage);

        if ($this->page > $totalPages && $totalItems > 0) {
            return $this->emptySitemap();
        }

        $items = $query->select(['id', $lastmodColumn])
            ->skip($offset)
            ->take($perPage)
            ->get();

        if ($items->isEmpty()) {
            return $this->emptySitemap();
        }

        $xml = $this->sitemapHeader();

        foreach ($items as $item) {
            $dateVal = $item->{$lastmodColumn};
            $lastmod = match (true) {
                $dateVal instanceof DateFormatter => Carbon::parse($dateVal->getTimestamp())->toAtomString(),
                $dateVal instanceof Carbon => $dateVal->toAtomString(),
                default => Carbon::parse($dateVal)->toAtomString(),
            };

            $xml .= $this->urlEntry(
                loc: $urlCallback($item),
                changefreq: $changefreq,
                priority: $priority,
                lastmod: $lastmod,
            );
        }

        $xml .= $this->sitemapFooter();

        return $xml;
    }

    private function sitemapHeader(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    }

    private function sitemapFooter(): string
    {
        return '</urlset>';
    }

    private function urlEntry(string $loc, string $changefreq, string $priority, string $lastmod): string
    {
        return "  <url>\n"
            . "    <loc>{$this->escape($loc)}</loc>\n"
            . "    <lastmod>{$lastmod}</lastmod>\n"
            . "    <changefreq>{$changefreq}</changefreq>\n"
            . "    <priority>{$priority}</priority>\n"
            . "  </url>\n";
    }

    private function emptySitemap(): string
    {
        return $this->sitemapHeader() . $this->sitemapFooter();
    }

    private function cacheKey(): string
    {
        if ($this->type === 'index') {
            return 'sitemap:index';
        }

        return "sitemap:{$this->type}:{$this->page}";
    }

    private function totalPagesForType(string $typeName, array $typeConfig): int
    {
        if (isset($typeConfig['urls'])) {
            return 1;
        }

        $cacheKey = "sitemap:{$typeName}:total_pages";

        if (config('seo.sitemap.cache.enabled')) {
            return Cache::remember(
                key: $cacheKey,
                ttl: config('seo.sitemap.cache.ttl'),
                callback: fn() => $this->countModelPages(typeName: $typeName, config: $typeConfig),
            );
        }

        return $this->countModelPages(typeName: $typeName, config: $typeConfig);
    }

    private function countModelPages(string $typeName, array $config): int
    {
        $modelClass = $config['model'];
        $scope = $config['scope'] ?? null;
        $perPage = config('seo.sitemap.items_per_page', 45000);

        $query = $modelClass::query();

        if ($scope && method_exists($modelClass, 'scope' . ucfirst($scope))) {
            $query->{$scope}();
        }

        $total = $query->count();

        return $total > 0 ? (int) ceil($total / $perPage) : 1;
    }

    private function isFeatureDisabled(array $config): bool
    {
        if (! isset($config['feature'])) {
            return false;
        }

        return ! config("features.{$config['feature']}.enabled", false);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
