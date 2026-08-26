<?php

return [
    'index_title' => 'Sitemap / SEO',

    'overview' => [
        'title' => 'Status Overview',
        'helper' => 'Current sitemap generation status and statistics. Google, Bing, Yandex and Baidu will discover URLs via robots.txt and these sitemap files.',
        'status' => 'Sitemap status',
        'on' => 'Enabled',
        'off' => 'Disabled',
        'last_generated' => 'Last generated',
        'never' => 'Never',
        'total_urls' => 'Total URLs',
        'chunks' => 'Chunks',
    ],

    'sections' => [
        'basic' => 'Basic Settings',
        'inclusion' => 'Included Content',
        'inclusion_helper' => 'Enable or disable content types and configure per-type limits, change frequency and priority.',
        'exclusion' => 'Exclusion Rules',
        'robots' => 'robots.txt',
        'actions' => 'Maintenance Actions',
        'actions_helper' => 'Regenerate the sitemap immediately, or ping search engines to notify them of the new sitemap.',
    ],

    'form' => [
        'enabled' => 'Enable Sitemap',
        'enabled_helper' => 'Master switch. When disabled, /sitemap.xml returns 404 and the sitemap line is removed from robots.txt.',
        'seo_head_enabled' => 'Enable Public SEO Pages',
        'seo_head_enabled_helper' => 'Serve server-rendered SEO pages (meta, JSON-LD, content snapshot) to guests at public profile / post / job / product / story URLs. Required for search engines to index dynamic content.',
        'per_page' => 'URLs per chunk',
        'per_page_helper' => 'Maximum URLs in each chunk file (sitemap protocol allows up to 50,000).',
        'cache_ttl' => 'Cache TTL (minutes)',
        'cache_ttl_helper' => 'How long the generated sitemap is cached before automatic regeneration.',
        'limit' => 'Limit (0 = unlimited)',
        'changefreq' => 'Change frequency',
        'priority' => 'Priority (0.0 – 1.0)',
        'excluded_paths' => 'Excluded paths',
        'excluded_paths_helper' => 'One pattern per line. Prefix match against the URL path (e.g. /settings). To use a regex, wrap it in slashes: /^\/private\//.',
        'robots_sitemap_line' => 'Add Sitemap line to robots.txt',
        'robots_sitemap_line_helper' => 'When enabled, the current sitemap URL is appended to public/robots.txt automatically.',
        'robots_custom' => 'Custom robots.txt content (optional)',
        'robots_custom_helper' => 'If filled, this content is used as the robots.txt body (the Sitemap line is still appended when enabled).',
        'robots_preview' => 'Current public/robots.txt',
    ],

    'types' => [
        'static' => 'Static pages',
        'users' => 'Profiles',
        'posts' => 'Posts',
        'stories' => 'Stories',
        'jobs' => 'Jobs',
        'products' => 'Products',
    ],

    'actions' => [
        'regenerate' => 'Regenerate Now',
        'ping_google' => 'Ping Google',
        'ping_bing' => 'Ping Bing',
        'google_pinged' => 'Google last pinged',
        'bing_pinged' => 'Bing last pinged',
    ],

    'engines' => [
        'google' => 'Google',
        'bing' => 'Bing',
    ],

    'flash' => [
        'saved' => 'Sitemap settings saved: cache rebuilt and robots.txt updated.',
        'regenerated' => 'Sitemap regenerated successfully — :count URLs in total.',
        'ping_success' => ':engine notified successfully.',
        'ping_failed' => 'Failed to notify :engine. Please retry later.',
    ],
];