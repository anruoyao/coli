<?php

namespace App\Console\Commands\Meilisearch;

use App\Models\Post;
use App\Actions\Post\IndexPostAction;
use App\Services\Meilisearch\MeilisearchService;
use Illuminate\Console\Command;

class SyncIndexCommand extends Command
{
    protected $signature = 'meilisearch:sync
                            {--missing-only : Only index posts missing from MeiliSearch}
                            {--chunk=500 : Chunk size for processing}';

    protected $description = 'Sync database posts with MeiliSearch index (incremental)';

    public function handle(): int
    {
        $service = app(MeilisearchService::class);

        if (! $service->isAvailable()) {
            $this->error('MeiliSearch service is not available.');

            return self::FAILURE;
        }

        $missingOnly = $this->option('missing-only');
        $chunkSize = (int) $this->option('chunk');

        if ($missingOnly) {
            $this->syncMissingOnly(service: $service, chunkSize: $chunkSize);
        } else {
            $this->syncAll(service: $service, chunkSize: $chunkSize);
        }

        return self::SUCCESS;
    }

    private function syncMissingOnly(MeilisearchService $service, int $chunkSize): void
    {
        $this->info('Checking for missing posts in MeiliSearch...');

        $indexedIds = [];
        $offset = 0;

        do {
            $results = $service->search(
                indexName: 'posts',
                query: '',
                options: [
                    'limit' => 1000,
                    'offset' => $offset,
                    'attributesToRetrieve' => ['id'],
                ],
            );

            if (empty($results['hits'])) {
                break;
            }

            foreach ($results['hits'] as $hit) {
                $indexedIds[] = (int) $hit['id'];
            }

            $offset += 1000;
        } while (count($results['hits']) === 1000);

        $missingCount = 0;

        Post::active()->with('user')->whereNotIn('id', $indexedIds)->chunk($chunkSize, function ($posts) use (&$missingCount) {
            foreach ($posts as $post) {
                (new IndexPostAction(post: $post))->execute();
                $missingCount++;
            }
        });

        $this->info("Synced {$missingCount} missing posts.");
    }

    private function syncAll(MeilisearchService $service, int $chunkSize): void
    {
        $this->info('Syncing all active posts with MeiliSearch...');

        $total = Post::active()->count();
        $progressBar = $this->output->createProgressBar($total);
        $synced = 0;

        Post::active()->with('user')->chunk($chunkSize, function ($posts) use ($progressBar, &$synced) {
            foreach ($posts as $post) {
                (new IndexPostAction(post: $post))->execute();
                $synced++;
            }

            $progressBar->advance($posts->count());
        });

        $progressBar->finish();

        $this->newLine();
        $this->info("Synced {$synced} posts.");
    }
}
