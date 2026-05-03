<?php

namespace App\Actions\Post;

use App\Models\Post;
use App\Services\Meilisearch\MeilisearchService;

class DeindexPostAction
{
    private Post $post;

    private MeilisearchService $meilisearch;

    public function __construct(Post $post)
    {
        $this->post = $post;
        $this->meilisearch = app(MeilisearchService::class);
    }

    public function execute(): void
    {
        if (! $this->meilisearch->isAvailable()) {
            return;
        }

        $this->meilisearch->deleteDocument(
            indexName: 'posts',
            documentId: $this->post->id,
        );
    }
}
