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

    /*
    | 访客（未登录）API 限流
    | 维度：device_id Cookie 优先，回退 IP（见 AppServiceProvider 的 guest 限流器）。
    */
    'guest' => [
        'rate_per_minute' => env('SECURITY_GUEST_RATE_PER_MINUTE', 60),
    ],

    /*
    | 分类限流额度表（routes/api.php 以 throttle:api.{category} 引用）
    | - max：窗口内最大请求数；decay：窗口（分钟）
    | - key 维度：登录用户取 user_id；未登录回退 device_id Cookie / IP（AppServiceProvider 注册）
    | - 挂在 auth:sanctum 之后的组天然按用户计数，防止同 IP 多用户互相挤爆、单用户刷爆 IP 配额
    */
    'rate_limits' => [
        // 高频读轮询组：保持原有放宽额度
        'timeline'      => ['max' => env('SECURITY_RATE_TIMELINE', 240), 'decay' => 1],
        'messenger'     => ['max' => env('SECURITY_RATE_MESSENGER', 240), 'decay' => 1],

        // 常规读写组（原 60/min）
        'post-editor'   => ['max' => env('SECURITY_RATE_POST_EDITOR', 60), 'decay' => 1],
        'broadcasting'  => ['max' => env('SECURITY_RATE_BROADCASTING', 60), 'decay' => 1],
        'bootstrap'     => ['max' => env('SECURITY_RATE_BOOTSTRAP', 60), 'decay' => 1],
        'presence'      => ['max' => env('SECURITY_RATE_PRESENCE', 60), 'decay' => 1],
        'settings'      => ['max' => env('SECURITY_RATE_SETTINGS', 60), 'decay' => 1],
        'auth'          => ['max' => env('SECURITY_RATE_AUTH', 60), 'decay' => 1],
        'story-editor'  => ['max' => env('SECURITY_RATE_STORY_EDITOR', 60), 'decay' => 1],
        'stories'       => ['max' => env('SECURITY_RATE_STORIES', 60), 'decay' => 1],
        'profile'       => ['max' => env('SECURITY_RATE_PROFILE', 60), 'decay' => 1],
        'relations'     => ['max' => env('SECURITY_RATE_RELATIONS', 60), 'decay' => 1],
        'marketplace'   => ['max' => env('SECURITY_RATE_MARKETPLACE', 60), 'decay' => 1],
        'jobs'          => ['max' => env('SECURITY_RATE_JOBS', 60), 'decay' => 1],
        'admin'         => ['max' => env('SECURITY_RATE_ADMIN', 60), 'decay' => 1],
        'recommendations' => ['max' => env('SECURITY_RATE_RECOMMENDATIONS', 60), 'decay' => 1],
        'notifications' => ['max' => env('SECURITY_RATE_NOTIFICATIONS', 60), 'decay' => 1],
        'feedback'      => ['max' => env('SECURITY_RATE_FEEDBACK', 60), 'decay' => 1],
        'bookmarks'     => ['max' => env('SECURITY_RATE_BOOKMARKS', 60), 'decay' => 1],
        'pins'          => ['max' => env('SECURITY_RATE_PINS', 60), 'decay' => 1],

        // 搜索 / 翻译 / 资金类收紧（原 60 → 30）
        'explore'       => ['max' => env('SECURITY_RATE_EXPLORE', 30), 'decay' => 1],
        'autocompletes' => ['max' => env('SECURITY_RATE_AUTOCOMPLETE', 30), 'decay' => 1],
        'translator'    => ['max' => env('SECURITY_RATE_TRANSLATOR', 30), 'decay' => 1],
        'wallet'        => ['max' => env('SECURITY_RATE_WALLET', 30), 'decay' => 1],
        'tips'          => ['max' => env('SECURITY_RATE_TIPS', 30), 'decay' => 1],

        // AI 高成本（第三方 API 调用）
        'ai'            => ['max' => env('SECURITY_RATE_AI', 10), 'decay' => 1],

        // 公开组（无 auth:sanctum，key 落到 device_id / IP）
        'translations'  => ['max' => env('SECURITY_RATE_TRANSLATIONS', 60), 'decay' => 1],
        'system'        => ['max' => env('SECURITY_RATE_SYSTEM', 60), 'decay' => 1],
        'ads'           => ['max' => env('SECURITY_RATE_ADS', 60), 'decay' => 1],

        // 认证组内端点（原 throttle:10,60）
        'auth-password' => ['max' => env('SECURITY_RATE_AUTH_PASSWORD', 10), 'decay' => 60],
    ],

    /*
    | 全局 IP 闸门（L0）：每 IP 全 API 总请求上限，防跨端点分布式刷取绕过组级限制。
    | - 白名单 IP（逗号分隔）与健康检查路径豁免
    | - 中间件挂在 bootstrap/app.php 全局链 app.key 之后
    */
    'ip_gate' => [
        'enabled' => env('SECURITY_IP_GATE_ENABLED', true),
        'max_per_minute' => env('SECURITY_IP_GATE_MAX_PER_MINUTE', 600),
        'whitelist' => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_IP_GATE_WHITELIST', ''))))),
    ],

    /*
    | 限流事件（429 命中审计）落库与裁剪策略。
    */
    'throttle_events' => [
        'retention_days' => env('SECURITY_THROTTLE_EVENTS_RETENTION_DAYS', 7),
    ],

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

        // App 注册邮箱验证码
        'verification_code_expires_minutes' => env('SECURITY_VERIFICATION_CODE_EXPIRES_MIN', 10),  // 验证码有效期（分钟）
        'verification_code_resend_cooldown' => env('SECURITY_VERIFICATION_CODE_RESEND_COOLDOWN', 60), // 重发冷却（秒）
        'verification_code_max_attempts' => env('SECURITY_VERIFICATION_CODE_MAX_ATTEMPTS', 5),    // 单码最大错误尝试次数
        'verification_code_max_per_ip' => env('SECURITY_VERIFICATION_CODE_MAX_PER_IP', 10),       // 发码接口每 IP 每小时
    ],

    /*
    | 举报限流（双维度：账号 + IP）
    | 计数来源为 reports 表本身（DB 持久化，系统重启后限流状态不丢失）。
    */
    'reports' => [
        'max_per_day' => env('SECURITY_REPORTS_MAX_PER_DAY', 10),          // 同一用户/IP 窗口期内最多提交次数
        'window_hours' => env('SECURITY_REPORTS_WINDOW_HOURS', 24),       // 滑动窗口（小时）
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
    | max_hard / decay_hard: 可选第二桶（长窗口硬上限），两桶任一超限即拒绝
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

        /*
        | 以下为高成本/资金/安全敏感动作（企业级限流补强新增）。
        | 可选 max_hard/decay_hard 为第二桶（如短窗 10 分钟 + 长窗 24 小时双限制）。
        */

        // 视频上传（FFmpeg 转码 + 缩略图，成本极高）：短窗 + 长窗双桶
        'video-upload' => [
            'paths' => ['post/editor/media/video/upload'],
            'max' => 1, 'decay' => 600,
            'max_hard' => 10, 'decay_hard' => 86400,
        ],

        // 资金操作：防脚本刷转账/充值接口
        'wallet-transfer' => [
            'paths' => ['wallet/transfer'],
            'max' => 5, 'decay' => 60,
            'new_user_max' => 2, 'new_user_decay' => 60,
        ],
        'wallet-deposit' => [
            'paths' => ['wallet/deposit'],
            'max' => 5, 'decay' => 60,
            'new_user_max' => 2, 'new_user_decay' => 60,
        ],

        // 敏感资料变更（防账号劫持后的资料篡改 / 验证码轰炸）
        'email-change' => [
            'paths' => ['settings/email/update'],
            'max' => 3, 'decay' => 3600,
            'new_user_max' => 1, 'new_user_decay' => 3600,
        ],
        'phone-change' => [
            'paths' => ['settings/phone/update'],
            'max' => 3, 'decay' => 3600,
            'new_user_max' => 1, 'new_user_decay' => 3600,
        ],

        // AI 生成（第三方 API 成本）。当前仅 greeting-message（App 启动拉取），
        // 未来新增生成类端点时须同步收紧。
        'ai-generate' => [
            'paths' => ['ai/greeting-message'],
            'max' => 5, 'decay' => 3600,
            'new_user_max' => 2, 'new_user_decay' => 3600,
        ],
    ],
];