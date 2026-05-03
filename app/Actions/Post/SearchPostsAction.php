<?php

namespace App\Actions\Post;

use App\Models\Post;
use App\Services\Meilisearch\MeilisearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchPostsAction
{
    private MeilisearchService $meilisearch;

    private string $query;

    private int $page;

    private int $perPage;

    private ?string $sortBy;

    private array $filters;

    public function __construct(
        string $query = '',
        int $page = 1,
        int $perPage = 30,
        ?string $sortBy = null,
        array $filters = [],
    ) {
        $this->meilisearch = app(MeilisearchService::class);
        $this->query = $query;
        $this->page = max(1, $page);
        $this->perPage = min(100, max(1, $perPage));
        $this->sortBy = $sortBy;
        $this->filters = $filters;
    }

    public function execute(): array
    {
        $cacheKey = $this->buildCacheKey();

        if (config('meilisearch.cache.enabled') && $this->query !== '') {
            $cached = Cache::get(key: $cacheKey);

            if ($cached !== null) {
                Log::debug('meilisearch: cache hit', [
                    'query' => $this->query,
                    'page' => $this->page,
                ]);

                return $cached;
            }
        }

        $results = $this->searchViaMeilisearch();

        if ($results === null) {
            $results = $this->searchViaDatabase();
        }

        if (config('meilisearch.cache.enabled') && $this->query !== '') {
            Cache::put(
                key: $cacheKey,
                value: $results,
                ttl: config('meilisearch.cache.ttl'),
            );
        }

        return $results;
    }

    private function searchViaMeilisearch(): ?array
    {
        if (! $this->meilisearch->isAvailable()) {
            return null;
        }

        $options = [
            'limit' => $this->perPage,
            'offset' => ($this->page - 1) * $this->perPage,
            'attributesToRetrieve' => ['id'],
            'attributesToHighlight' => ['content'],
        ];

        if ($this->sortBy) {
            $options['sort'] = [$this->sortBy];
        }

        $filterExpressions = $this->buildFilterExpressions();
        if ($filterExpressions) {
            $options['filter'] = $filterExpressions;
        }

        $searchResult = $this->meilisearch->search(
            indexName: 'posts',
            query: $this->query,
            options: $options,
        );

        if ($searchResult === null) {
            return null;
        }

        $postIds = collect($searchResult['hits'])->pluck('id')->toArray();

        if (empty($postIds)) {
            return [
                'ids' => [],
                'total' => $searchResult['estimatedTotalHits'] ?? 0,
                'has_more' => false,
            ];
        }

        return [
            'ids' => $postIds,
            'total' => $searchResult['estimatedTotalHits'] ?? 0,
            'has_more' => ($this->page * $this->perPage) < ($searchResult['estimatedTotalHits'] ?? 0),
        ];
    }

    private function searchViaDatabase(): array
    {
        Log::info('meilisearch: falling back to database search', [
            'query' => $this->query,
            'page' => $this->page,
        ]);

        $query = Post::active()->where('status', 'active');

        if ($this->query !== '') {
            $query->where(function ($q) {
                $q->whereLike('content', "%{$this->query}%");
            });
        }

        $total = $query->count();
        $posts = $query->orderByDesc('created_at')
            ->skip(($this->page - 1) * $this->perPage)
            ->take($this->perPage)
            ->pluck('id')
            ->toArray();

        return [
            'ids' => $posts,
            'total' => $total,
            'has_more' => ($this->page * $this->perPage) < $total,
        ];
    }

    private function buildCacheKey(): string
    {
        $payload = [
            'q' => $this->query,
            'p' => $this->page,
            'pp' => $this->perPage,
            's' => $this->sortBy,
            'f' => $this->filters,
        ];

        return 'ms_search:' . md5(serialize($payload));
    }

    private function buildFilterExpressions(): array
    {
        $expressions = [];

        if (! empty($this->filters['user_id'])) {
            $expressions[] = 'user_id = ' . (int) $this->filters['user_id'];
        }

        if (! empty($this->filters['type'])) {
            $expressions[] = 'type = ' . $this->filters['type'];
        }

        if (isset($this->filters['is_sensitive'])) {
            $expressions[] = 'is_sensitive = ' . ($this->filters['is_sensitive'] ? 'true' : 'false');
        }

        return $expressions;
    }
}
