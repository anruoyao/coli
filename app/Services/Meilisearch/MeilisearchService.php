<?php

namespace App\Services\Meilisearch;

use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Illuminate\Support\Facades\Log;

class MeilisearchService
{
    private ?Client $client = null;

    private bool $available = true;

    public function __construct()
    {
        $this->initialize();
    }

    private function initialize(): void
    {
        try {
            $this->client = new Client(
                url: config('meilisearch.host'),
                apiKey: config('meilisearch.key') ?: null,
            );

            $this->client->health();
        } catch (\Throwable $e) {
            $this->available = false;
            $this->client = null;

            Log::warning('meilisearch: connection failed, falling back to database search', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function isAvailable(): bool
    {
        return $this->available && $this->client !== null;
    }

    public function getIndex(string $indexName): ?Indexes
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $config = config("meilisearch.indexes.{$indexName}");

            return $this->client->index(uid: $config['name']);
        } catch (\Throwable $e) {
            Log::error('meilisearch: failed to get index', [
                'index' => $indexName,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function configureIndex(string $indexName): void
    {
        $index = $this->getIndex(indexName: $indexName);

        if (! $index) {
            return;
        }

        $config = config("meilisearch.indexes.{$indexName}");

        try {
            $index->updateSearchableAttributes(
                searchableAttributes: $config['searchable_attributes']
            );

            $index->updateFilterableAttributes(
                filterableAttributes: $config['filterable_attributes']
            );

            $index->updateSortableAttributes(
                sortableAttributes: $config['sortable_attributes']
            );

            $index->updateRankingRules(
                rankingRules: $config['ranking_rules']
            );

            $index->updateTypoTolerance(
                typoTolerance: $config['typo_tolerance']
            );

            Log::info('meilisearch: index configured', [
                'index' => $indexName,
            ]);
        } catch (\Throwable $e) {
            Log::error('meilisearch: failed to configure index', [
                'index' => $indexName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function addDocuments(string $indexName, array $documents): void
    {
        $index = $this->getIndex(indexName: $indexName);

        if (! $index) {
            return;
        }

        try {
            $config = config("meilisearch.indexes.{$indexName}");

            $index->addDocuments(
                documents: $documents,
                primaryKey: $config['primary_key'],
            );
        } catch (\Throwable $e) {
            Log::error('meilisearch: failed to add documents', [
                'index' => $indexName,
                'count' => count($documents),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updateDocuments(string $indexName, array $documents): void
    {
        $index = $this->getIndex(indexName: $indexName);

        if (! $index) {
            return;
        }

        try {
            $config = config("meilisearch.indexes.{$indexName}");

            $index->updateDocuments(
                documents: $documents,
                primaryKey: $config['primary_key'],
            );
        } catch (\Throwable $e) {
            Log::error('meilisearch: failed to update documents', [
                'index' => $indexName,
                'count' => count($documents),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleteDocument(string $indexName, int|string $documentId): void
    {
        $index = $this->getIndex(indexName: $indexName);

        if (! $index) {
            return;
        }

        try {
            $index->deleteDocument(documentId: $documentId);
        } catch (\Throwable $e) {
            Log::error('meilisearch: failed to delete document', [
                'index' => $indexName,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleteAllDocuments(string $indexName): void
    {
        $index = $this->getIndex(indexName: $indexName);

        if (! $index) {
            return;
        }

        try {
            $index->deleteAllDocuments();
        } catch (\Throwable $e) {
            Log::error('meilisearch: failed to delete all documents', [
                'index' => $indexName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function search(string $indexName, string $query, array $options = []): ?array
    {
        $index = $this->getIndex(indexName: $indexName);

        if (! $index) {
            return null;
        }

        try {
            return $index->search(
                query: $query,
                searchParams: $options,
            )->toArray();
        } catch (\Throwable $e) {
            Log::error('meilisearch: search failed', [
                'index' => $indexName,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
