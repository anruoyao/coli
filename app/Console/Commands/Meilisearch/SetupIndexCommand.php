<?php

namespace App\Console\Commands\Meilisearch;

use App\Models\Post;
use Carbon\Carbon;
use App\Services\Meilisearch\MeilisearchService;
use Illuminate\Console\Command;

class SetupIndexCommand extends Command
{
    protected $signature = 'meilisearch:setup
                            {--reindex : Reindex all posts after setup}
                            {--chunk=500 : Chunk size for reindexing}';

    protected $description = 'Configure MeiliSearch indexes and optionally reindex all posts';

    public function handle(): int
    {
        $service = app(MeilisearchService::class);

        if (! $service->isAvailable()) {
            $this->error('MeiliSearch service is not available.');

            return self::FAILURE;
        }

        $this->info('Configuring MeiliSearch posts index...');
        $service->configureIndex(indexName: 'posts');
        $this->info('Posts index configured successfully.');

        if ($this->option('reindex')) {
            $this->reindexAllPosts(service: $service);
        }

        return self::SUCCESS;
    }

    private function reindexAllPosts(MeilisearchService $service): void
    {
        $this->info('Clearing existing documents...');
        $service->deleteAllDocuments(indexName: 'posts');

        $chunkSize = (int) $this->option('chunk');

        $this->info("Reindexing all active posts in chunks of {$chunkSize}...");

        $progressBar = $this->output->createProgressBar(Post::active()->count());

        Post::active()->with('user')->chunk($chunkSize, function ($posts) use ($progressBar) {
            $documents = [];

            foreach ($posts as $post) {
                $documents[] = [
                    'id' => $post->id,
                    'content' => strip_tags($post->content),
                    'user_id' => $post->user_id,
                    'username' => $post->user?->username ?? '',
                    'display_name' => $post->user?->name ?? '',
                    'type' => $post->type->value,
                    'is_sensitive' => $post->is_sensitive,
                    'created_at_timestamp' => Carbon::parse($post->created_at->getTimestamp())->timestamp,
                    'comments_count' => $post->comments_count,
                    'bookmarks_count' => $post->bookmarks_count,
                    'views_count' => $post->views_count,
                ];
            }

            app(MeilisearchService::class)->addDocuments(
                indexName: 'posts',
                documents: $documents,
            );

            $progressBar->advance($posts->count());
        });

        $progressBar->finish();

        $this->newLine();
        $this->info('Reindexing completed.');
    }
}
