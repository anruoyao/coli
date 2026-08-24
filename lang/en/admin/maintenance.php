<?php

return [
    'index_title' => 'Maintenance (Emergency)',
    'form' => [
        'enabled' => 'Enable maintenance mode',
        'enabled_helper' => 'When enabled, all users are forced offline and the app shows a maintenance screen; API requests return 503. The admin area stays accessible so you can turn it off.',
        'message' => 'Maintenance notice',
        'message_placeholder' => 'e.g. We are performing emergency maintenance and expect to be back within 2 hours…',
        'message_helper' => 'Shown in the app maintenance screen and API responses. Leave empty for the default message.',
        'until' => 'Expected recovery time',
        'until_helper' => 'Optional expected recovery time shown to users. Leave empty if unknown.',
    ],
    'flash' => [
        'enabled' => 'Maintenance mode enabled. All online users have been signed out!',
        'disabled' => 'Maintenance mode disabled. Service restored.',
    ],
];