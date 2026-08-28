<?php

/*
|--------------------------------------------------------------------------
| API 安全防护配置（P0 防刷数据/防攻击）
|--------------------------------------------------------------------------
| 供 AbuseGuardMiddleware / 登录注册限流 / Token 治理使用。
| 原则：客户端代码（App/网页 bundle）不包含任何可信秘密，全部防线服务端化。
*/

return [

    // 新账号保护窗口：注册后 N 小时内的账号执行更严格的动作限流
    'new_user_window_hours' => env('SECURITY_NEW_USER_WINDOW_HOURS', 24),

    // 同内容幂等去重窗口（秒）：同一用户对同一动作重复提交相同内容直接拒绝
    'duplicate_content_window_seconds' => env('SECURITY_DUPLICATE_CONTENT_WINDOW', 10),

    /*
    | 认证限流
    | - 登录：IP 维度 + 账号失败次数字段每 15 分钟
    | - 注册 / 忘记密码：IP 维度
    | - 每账号活跃 token 上限（超出删除最旧）
    */
    'auth' => [
        'login_max_attempts_per_ip' => env('SECURITY_LOGIN_MAX_PER_IP', 10),                    // 每 IP 每 5 分钟
        'login_max_failures_per_account' => env('SECURITY_LOGIN_MAX_FAILURES_PER_ACCOUNT', 5),  // 每账号连续失败数（15 分钟窗口）
        'register_max_per_ip' => env('SECURITY_REGISTER_MAX_PER_IP', 10),                       // 每 IP 每小时
        'forgot_max_per_ip' => env('SECURITY_FORGOT_MAX_PER_IP', 5),                            // 每 IP 每小时
        'max_tokens_per_account' => env('SECURITY_MAX_TOKENS_PER_ACCOUNT', 10),                 // 每账号活跃 token 上限
    ],

    // 一次性邮箱域名黑名单（注册拦截）
    'disposable_email_domains' => [
        'mailinator.com', 'mailinator.net', 'mailinator.org',
        '10minutemail.com', '10minutemail.net', '10minutemail.org',
        'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
        'sharklasers.com', 'maildrop.cc', 'mailnesia.com', 'throwawaymail.com',
        'temp-mail.org', 'tempmail.com', 'yopmail.com', 'dispostable.com',
        'trashmail.com', 'spamgourmet.com', 'getnada.com', 'emailnator.com',
        'mailmetrash.com', 'mintemail.com', 'mohmal.com', 'mailmoat.com',
        'burnermail.io', 'fakeinbox.com', 'tmail.ws', 'inboxbear.com',
    ],

    /*
    | 客户端请求密钥（X-App-Key）—— 准入门槛 + 击杀开关，非安全边界。
    |
    | 定位：把「拿 API 文档 + Postman 直接打」的非官方脚本挡在门外；密钥必然
    | 可从 APK/网页 bundle 提取，因此它不承载机密语义，只是「官方客户端标识」。
    | 轮换方式：SECURITY_APP_KEYS 支持逗号分隔多 key，泄露的 key 从列表移除即
    | 全体失效（配合发新版客户端换新 key）。
    |
    | enabled：总开关（推新发布时先置 false 兼容旧客户端，再切 true）。
    | keys 为空列表时视为未配置，中间件放行（避免未配置环境误伤）。
    */
    'app_key' => [
        'enabled' => env('SECURITY_APP_KEY_ENABLED', true),
        'keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_APP_KEYS', 'clbPK-8f3k2m9xq4w7v1t6a5s0d2n8h4j6y1c'))))),
    ],

    /*
    | 风控动作限流（AbuseGuardMiddleware）
    | paths:  相对 /api/ 的路径（精确或前缀匹配，如 'post/editor/media/' 匹配所有子路径）
    | methods: 可选，仅对指定 HTTP 方法生效（默认全部）
    | max / decay:          普通账号在 decay 秒内的最大次数
    | new_user_max / new_user_decay: 新账号（注册 < new_user_window_hours）限定，缺省沿用普通值
    */
    'actions' => [
        'post-create' => [
            'paths' => ['post/editor/create', 'post/editor/gif/create', 'post/editor/poll/create'],
            'max' => 10, 'decay' => 600,
            'new_user_max' => 3, 'new_user_decay' => 600,
        ],
        'comment-create' => [
            'paths' => ['timeline/post/comment/create'],
            'max' => 30, 'decay' => 600,
            'new_user_max' => 10, 'new_user_decay' => 600,
        ],
        'message-send' => [
            'paths' => ['messenger/send', 'messenger/chats/launcher-send'],
            'max' => 120, 'decay' => 600,
            'new_user_max' => 30, 'new_user_decay' => 600,
        ],
        'chat-create' => [
            'paths' => ['messenger/chats/create', 'messenger/chats/launch'],
            'max' => 20, 'decay' => 3600,
            'new_user_max' => 5, 'new_user_decay' => 3600,
        ],
        'follow' => [
            'paths' => ['relations/follow/user'],
            'max' => 100, 'decay' => 3600,
            'new_user_max' => 20, 'new_user_decay' => 3600,
        ],
        'reaction' => [
            'paths' => ['timeline/post/reaction/add', 'timeline/comment/reaction/add'],
            'max' => 150, 'decay' => 600,
            'new_user_max' => 40, 'new_user_decay' => 600,
        ],
        'story-create' => [
            'paths' => ['story/editor/create'],
            'max' => 20, 'decay' => 3600,
            'new_user_max' => 5, 'new_user_decay' => 3600,
        ],
        'upload' => [
            'paths' => ['post/editor/media/', 'story/editor/media/'],
            'methods' => ['post', 'put'],
            'max' => 60, 'decay' => 3600,
            'new_user_max' => 10, 'new_user_decay' => 3600,
        ],
        'bookmark' => [
            'paths' => ['timeline/post/bookmarks/add'],
            'max' => 120, 'decay' => 3600,
            'new_user_max' => 30, 'new_user_decay' => 3600,
        ],
        'poll-vote' => [
            'paths' => ['timeline/post/poll/vote'],
            'max' => 120, 'decay' => 600,
            'new_user_max' => 30, 'new_user_decay' => 600,
        ],
    ],
];