<?php

return [
    'index_title' => 'Versioning',
    'list_helper' => 'Manage mobile app versions per platform. Active versions are served to the app client via the /api/system/version/check endpoint.',
    'actions' => [
        'create' => 'New version',
        'back' => 'Back to list',
    ],
    'table' => [
        'version' => 'Code',
        'platform' => 'Platform',
        'download' => 'Download link',
        'forced' => 'Forced',
        'status' => 'Status',
        'released_at' => 'Released at',
        'online' => 'Online',
        'offline' => 'Offline',
        'immediate' => 'Immediate',
        'empty' => 'No versions yet. Create the first one with the "New version" button above.',
    ],
    'form' => [
        'code' => 'Version code',
        'code_helper' => 'Semantic version, e.g. 2.1.0. Must be unique per platform.',
        'platform' => 'Platform',
        'platform_helper' => 'The client platform this version targets.',
        'download_url' => 'Package download link',
        'download_url_helper' => 'External link opened by the "Update now" button inside the app (APK / App Store / TestFlight, etc.).',
        'notes' => 'Release notes',
        'notes_placeholder' => 'One note per line, rendered line by line in the app…',
        'notes_helper' => 'Release notes shown in the app update dialog. Multiple lines supported.',
        'released_at' => 'Release time',
        'released_at_helper' => 'Leave empty for immediate effect; set a future time to schedule the release.',
        'is_forced' => 'Force update',
        'is_forced_helper' => 'When enabled, clients on older versions are forced to upgrade (cannot be dismissed).',
        'is_active' => 'Active',
        'is_active_helper' => 'Only active versions participate in version checks; disable to take a version offline.',
    ],
    'prompts' => [
        'delete' => [
            'content' => 'Delete this version record? This action cannot be undone.',
        ],
    ],
    'flash' => [
        'created' => 'Version created.',
        'updated' => 'Version updated.',
        'deleted' => 'Version deleted.',
    ],
];