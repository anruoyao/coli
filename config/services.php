<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | FCM（Firebase Cloud Messaging）— App 端系统推送（预留）
    |--------------------------------------------------------------------------
    | App 的项目推送目前以「站内通知」为主。若未来要下发系统级推送，
    | 在此配置 FIREBASE_SERVER_KEY，并在营销配置中开启 MARKETING_FCM_ENABLED，
    | 即可通过 MarketingFcmSender 按主题下发（data 载荷含 title/body，与 App 端
    | FirebaseNotificationManager 约定一致）。未配置时所有 FCM 调用自动跳过。
    */
    'fcm' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
        'topic' => env('FIREBASE_TOPIC', 'chatter'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'giphy' => [
        'api_key' => env('GIPHY_API_KEY'),
    ],
    'vonage' => [
        'api_key' => env('VONAGE_API_KEY'),
        'api_secret' => env('VONAGE_API_SECRET'),
        'from_number' => env('VONAGE_FROM_NUMBER'),
    ],
    'smsaero' => [
        'login' => env('SMSAERO_LOGIN'),
        'api_key' => env('SMSAERO_API_KEY'),
        'sender_name' => env('SMSAERO_SENDER_NAME'),
        'channel' => env('SMSAERO_CHANNEL'),
    ],
    'ipinfo' => [
        'token' => env('IPINFO_TOKEN'),
    ],
    'translation' => [
        'api_url' => env('TRANSLATION_SERVICE_API_URL'),
        'api_key' => env('TRANSLATION_SERVICE_API_KEY'),
        'service' => env('TRANSLATION_SERVICE'),
        'logo' => env('TRANSLATION_SERVICE_LOGO'),
        'name' => env('TRANSLATION_SERVICE_NAME'),
        'url' => env('TRANSLATION_SERVICE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | NSFW 检测微服务（nsfw-service，NudeNet）
    |--------------------------------------------------------------------------
    | 帖子媒体 NSFW 自动识别引擎。与本服务同机部署，仅监听 127.0.0.1。
    | 功能开关/阈值/触发标签等业务配置在 NsfwDetectionSettings（后台可调），
    | 经 SettingsServiceProvider 注入为 config('features.nsfw_detection.*')。
    */
    'nsfw_detection' => [
        'url' => env('NSFW_DETECTION_URL', 'http://127.0.0.1:8300'),
        'timeout' => env('NSFW_DETECTION_TIMEOUT', 30),
        'connect_timeout' => env('NSFW_DETECTION_CONNECT_TIMEOUT', 3),
    ],
];
