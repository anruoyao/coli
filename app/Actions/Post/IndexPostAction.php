<?php

namespace App\Actions\Post;

use App\Models\Post;
use Carbon\Carbon;
use App\Services\Meilisearch\MeilisearchService;

class IndexPostAction
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

        $document = [
            'id' => $this->post->id,
            'content' => strip_tags($this->post->content),
            'user_id' => $this->post->user_id,
            'username' => $this->post->user?->username ?? '',
            'display_name' => $this->post->user?->name ?? '',
            'type' => $this->post->type->value,
            'is_sensitive' => $this->post->is_sensitive,
            'created_at_timestamp' => Carbon::parse($this->post->created_at->getTimestamp())->timestamp,
            'comments_count' => $this->post->comments_count,
            'bookmarks_count' => $this->post->bookmarks_count,
            'views_count' => $this->post->views_count,
        ];

        $this->meilisearch->addDocuments(
            indexName: 'posts',
            documents: [$document],
        );
    }
}
