<?php

namespace App\Console\Commands\Seo;

use Illuminate\Console\Command;
use App\Actions\Seo\GenerateSitemapAction;
use Illuminate\Support\Facades\Cache;

class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate
                            {--type= : Generate sitemap for a specific type}
                            {--clear : Clear existing cache before generating}';

    protected $description = 'Pre-generate and cache the XML sitemap';

    public function handle(): int
    {
        if ($this->option('clear')) {
            $this->info('Clearing sitemap cache...');

            $indexAction = new GenerateSitemapAction(type: 'index');
            $indexAction->clearCache();

            $this->info('Cache cleared.');
        }

        $type = $this->option('type');

        if ($type) {
            return $this->generateSingle(type: $type);
        }

        return $this->generateAll();
    }

    private function generateAll(): int
    {
        $this->info('Generating sitemap index...');

        $indexAction = new GenerateSitemapAction(type: 'index');
        $indexAction->clearCache();
        $indexAction->execute();

        $this->info('Sitemap index generated.');

        foreach (config('seo.sitemap.types') as $typeName => $typeConfig) {
            if (! ($typeConfig['enabled'] ?? false)) {
                continue;
            }

            $this->generateAllPagesForType(typeName: $typeName, typeConfig: $typeConfig);
        }

        $this->info('Sitemap generation complete.');

        return self::SUCCESS;
    }

    private function generateSingle(string $type): int
    {
        $types = config('seo.sitemap.types');

        if (! isset($types[$type])) {
            $this->error("Unknown sitemap type: {$type}");

            return self::FAILURE;
        }

        if (! ($types[$type]['enabled'] ?? false)) {
            $this->warn("Sitemap type '{$type}' is disabled.");

            return self::FAILURE;
        }

        $this->generateAllPagesForType(typeName: $type, typeConfig: $types[$type]);

        $this->info("Sitemap for '{$type}' generated.");

        return self::SUCCESS;
    }

    private function generateAllPagesForType(string $typeName, array $typeConfig): void
    {
        $this->info("Generating sitemap for '{$typeName}'...");

        if (isset($typeConfig['urls'])) {
            $action = new GenerateSitemapAction(type: $typeName, page: 1);
            $action->clearCache();
            $action->execute();

            return;
        }

        $modelClass = $typeConfig['model'];
        $scope = $typeConfig['scope'] ?? null;
        $perPage = config('seo.sitemap.items_per_page', 45000);

        $query = $modelClass::query();

        if ($scope && method_exists($modelClass, 'scope' . ucfirst($scope))) {
            $query->{$scope}();
        }

        $totalItems = $query->count();
        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 1;

        $progressBar = $this->output->createProgressBar($totalPages);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');

        for ($page = 1; $page <= $totalPages; $page++) {
            $progressBar->setMessage("Page {$page}/{$totalPages}");

            $action = new GenerateSitemapAction(type: $typeName, page: $page);
            $action->clearCache();
            $action->execute();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        Cache::forget("sitemap:{$typeName}:total_pages");
    }
}
