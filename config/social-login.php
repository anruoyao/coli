<?php

return [
    'enabled' => true,
    'providers' => [
        'google' => [
            'enabled' => env('GOOGLE_LOGIN_ENABLED', false),
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/google'
        ],
        'apple' => [
            'enabled' => env('APPLE_LOGIN_ENABLED', false),
            'client_id' => env('APPLE_CLIENT_ID'),
            'client_secret' => env('APPLE_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/apple'
        ],
        'telegram' => [
            'enabled' => env('TELEGRAM_LOGIN_ENABLED', false),
            'client_id' => env('TELEGRAM_CLIENT_ID'),
            'client_secret' => env('TELEGRAM_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/telegram'
        ],
        'twitter' => [
            'enabled' => env('TWITTER_LOGIN_ENABLED', false),
            'client_id' => env('TWITTER_CLIENT_ID'),
            'client_secret' => env('TWITTER_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/twitter',
        ],
        'facebook' => [
            'enabled' => env('FACEBOOK_LOGIN_ENABLED', false),
            'client_id' => env('FACEBOOK_CLIENT_ID'),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/facebook'
        ],
        'tiktok' => [
            'enabled' => env('TIKTOK_LOGIN_ENABLED', false),
            'client_id' => env('TIKTOK_CLIENT_ID'),
            'client_secret' => env('TIKTOK_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/tiktok'
        ],
        'discord' => [
            'enabled' => env('DISCORD_LOGIN_ENABLED', false),
            'client_id' => env('DISCORD_CLIENT_ID'),
            'client_secret' => env('DISCORD_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/discord'
        ],
        'linkedin' => [
            'enabled' => env('LINKEDIN_LOGIN_ENABLED', false),
            'client_id' => env('LINKEDIN_CLIENT_ID'),
            'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/linkedin'
        ],
        'vk' => [
            'enabled' => env('VK_LOGIN_ENABLED', false),
            'client_id' => env('VK_CLIENT_ID'),
            'client_secret' => env('VK_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/vk'
        ],
        'microsoft' => [
            'enabled' => env('MICROSOFT_LOGIN_ENABLED', false),
            'client_id' => env('MICROSOFT_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
            'redirect' => 'social-login/callback/microsoft'
        ]
    ]
];