<?php

return [
    'index_title' => 'Online Users',
    'list_helper' => 'Reflects the user sessions currently using the platform (Web browser / Android / iOS app). Auto-refreshes every 30 seconds.',
    'search_placeholder' => 'Search name / username / email…',
    'stats' => [
        'total' => 'Online Now',
        'web' => 'Web',
        'android' => 'Android',
        'ios' => 'iOS',
    ],
    'tabs' => [
        'all' => 'All',
        'web' => 'Web',
        'android' => 'Android',
        'ios' => 'iOS',
        'live' => 'Live',
        'analytics' => 'Analytics',
    ],
    'table' => [
        'user' => 'User',
        'platform' => 'Platform',
        'origin' => 'IP / Location',
        'online_since' => 'Online For',
        'last_seen' => 'Last Seen',
    ],
    'actions' => [
        'refresh' => 'Refresh',
        'view' => 'View',
        'export' => 'Export CSV',
    ],
    'prompts' => [
        'kick' => [
            'content' => 'Force this session offline? All App login tokens of this user will be revoked (web sessions stay, but no longer counted as online).',
        ],
    ],
    'analytics' => [
        'dau' => 'DAU (24h)',
        'wau' => 'WAU (7d)',
        'mau' => 'MAU (30d)',
        'today_peak' => 'Today Peak',
        'hourly_trend' => '24h Online Trend',
        'hourly_hint' => 'Hourly snapshots (presence:aggregate), online sessions per platform.',
        'daily_trend' => '7d Online Volume',
        'daily_hint' => 'Daily online session volume (sum) and single-day peak (max hourly bucket).',
        'empty_hint' => 'No snapshots yet. Snapshots are aggregated hourly by the system — refresh later to see the trend.',
        'series_total' => 'All',
        'series_daily_total' => 'Daily Total',
        'series_daily_peak' => 'Daily Peak',
    ],
    'export' => [
        'headers' => [
            'user' => 'User',
            'platform' => 'Platform',
            'platform_detail' => 'Platform Detail',
            'ip' => 'IP',
            'location' => 'Location',
        ],
    ],
];