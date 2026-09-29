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
        'actions_helper' => 'Regenerate the sitemap immediately; push indexed URLs to Bing / Yandex via IndexNow; Google retired its automatic ping endpoint and requires a one-time manual submission in Search Console.',
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
        'ping_bing' => 'Push to Bing / Yandex (IndexNow)',
        'ping_google' => 'Google Submission Guide',
        'bing_pinged' => 'IndexNow last pushed',
        'indexnow_hint' => 'IndexNow requires no registration: instantly submit all URLs currently in the sitemap to Bing, Yandex and other supporting engines (the key file is verified automatically on first push).',
        'google_hint' => 'Google shut down its automatic sitemap ping in 2023. Submit the sitemap once manually on the Sitemaps page of Search Console (it is then recrawled automatically):',
    ],

    'flash' => [
        'saved' => 'Sitemap settings saved: cache rebuilt and robots.txt updated.',
        'regenerated' => 'Sitemap regenerated successfully — :count URLs in total.',
        'indexnow_success' => 'IndexNow accepted the submission — :count URLs pushed. Bing / Yandex will crawl them shortly.',
        'indexnow_failed' => 'IndexNow push failed (endpoint returned HTTP :status). Please retry later.',
        'google_manual' => 'Google does not support automatic pings: open Google Search Console → select your site → Sitemaps → submit :url (one-time; Google recrawls it automatically afterwards).',
    ],
];