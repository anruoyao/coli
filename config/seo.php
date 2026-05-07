<?php

return [
    'sitemap' => [
        'cache' => [
            'enabled' => true,
            'ttl' => 86400,
        ],
        'items_per_page' => 45000,
        'types' => [
            'posts' => [
                'enabled' => true,
                'model' => \App\Models\Post::class,
                'scope' => 'active',
                'url_callback' => fn($item) => url('/publication/' . $item->hash_id),
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'lastmod_column' => 'updated_at',
            ],
            'profiles' => [
                'enabled' => true,
                'model' => \App\Models\User::class,
                'scope' => 'active',
                'url_callback' => fn($item) => url('/@' . $item->username),
                'changefreq' => 'daily',
                'priority' => '0.9',
                'lastmod_column' => 'updated_at',
            ],
            'products' => [
                'enabled' => true,
                'model' => \App\Models\Product::class,
                'scope' => 'active',
                'url_callback' => fn($item) => url('/marketplace/product/' . $item->hash_id),
                'changefreq' => 'weekly',
                'priority' => '0.7',
                'lastmod_column' => 'updated_at',
                'feature' => 'marketplace',
            ],
            'jobs' => [
                'enabled' => true,
                'model' => \App\Models\JobListing::class,
                'scope' => 'active',
                'url_callback' => fn($item) => url('/jobs/' . $item->hash_id),
                'changefreq' => 'weekly',
                'priority' => '0.7',
                'lastmod_column' => 'updated_at',
                'feature' => 'jobs',
            ],
            'static' => [
                'enabled' => true,
                'urls' => [
                    ['loc' => '/', 'changefreq' => 'daily', 'priority' => '1.0'],
                    ['loc' => '/explore', 'changefreq' => 'daily', 'priority' => '0.9'],
                    ['loc' => '/about-author', 'changefreq' => 'monthly', 'priority' => '0.5'],
                ],
            ],
        ],
    ],
];
