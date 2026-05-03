<?php

return [

    'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),

    'key' => env('MEILISEARCH_KEY', ''),

    'indexes' => [
        'posts' => [
            'name' => 'posts',
            'primary_key' => 'id',
            'searchable_attributes' => [
                'content',
                'username',
                'display_name',
            ],
            'filterable_attributes' => [
                'user_id',
                'type',
                'is_sensitive',
                'created_at_timestamp',
            ],
            'sortable_attributes' => [
                'created_at_timestamp',
                'comments_count',
                'bookmarks_count',
                'views_count',
            ],
            'ranking_rules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
                'created_at_timestamp:desc',
            ],
            'typo_tolerance' => [
                'enabled' => true,
                'min_word_size_for_typos' => [
                    'one_typo' => 5,
                    'two_typos' => 9,
                ],
            ],
        ],
    ],

    'cache' => [
        'enabled' => env('MEILISEARCH_CACHE_ENABLED', true),
        'ttl' => env('MEILISEARCH_CACHE_TTL', 300),
    ],

    'search' => [
        'debounce_ms' => 500,
        'per_page' => 30,
        'fallback_to_db' => env('MEILISEARCH_FALLBACK_TO_DB', true),
    ],
];
