<?php

return [
    // Do not change this value, it is set by the settings service provider.

    'default' => 'smtp',

    'mailers' => [
        'smtp' => [
            'transport'    => 'smtp',
            'host'         => '127.0.0.1',
            'port'         => 2525,
            'username'     => null,
            'password'     => null,
            'scheme'       => null,
            'local_domain' => 'http://localhost',
        ],
    ],

    'from' => [
        'address' => 'noreply@example.com',
        'name'    => 'ColibriPlus',
    ],
];
