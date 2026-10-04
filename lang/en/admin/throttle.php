<?php

return [
    'index_title' => 'API Rate Limiting Monitor',
    'list_helper' => 'HTTP 429 hits from the three rate-limiting layers: category throttles (throttle:api.*), abuse guard actions (abuse:*) and the global IP gate. Auto-refreshes every 30 seconds.',
    'search_placeholder' => 'Search identifier / path / IP…',
    'stats' => [
        'total' => '429 Hits Today',
        'user' => 'By User',
        'ip' => 'By IP',
        'gate' => 'Global Gate Hits',
    ],
    'tabs' => [
        'all' => 'All',
        'user' => 'User',
        'ip' => 'IP',
        'device' => 'Device',
        'global' => 'Global',
        'today' => 'Today',
        '24h' => '24 Hours',
        '7d' => '7 Days',
    ],
    'filter' => [
        'all_categories' => 'All categories',
    ],
    'table' => [
        'time' => 'Time',
        'dimension' => 'Dimension',
        'identifier' => 'Identifier',
        'category' => 'Category (limit / window)',
        'path' => 'Method & Path',
        'ip' => 'IP',
    ],
];
