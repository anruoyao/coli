<?php

return [
	'sounds' => [
		'notification_received' => 'assets/sounds/notifications/notification-received.mp3',
		'ui_feedback' => 'assets/sounds/notifications/ui-feedback.mp3'
	],
	'email' => [
		'enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', false),
	],
	'broadcast' => [
		'enabled' => env('NOTIFICATIONS_BROADCAST_ENABLED', true),
	],
	'push' => [
		'enabled' => env('NOTIFICATIONS_PUSH_ENABLED', false),
	],
	/*
	|--------------------------------------------------------------------------
	| App 内实时通知去重（24h 未读同源合并，防止通知页被刷屏）
	|--------------------------------------------------------------------------
	*/
	'deduplication' => [
		'enabled' => true,
		'unread_window_minutes' => 1440,
	],
	/*
	|--------------------------------------------------------------------------
	| 聚合 Digest 邮件
	|--------------------------------------------------------------------------
	| 社交互动（点赞/评论/关注/提及）不再逐条发邮件，而是窗口期内聚合为一封摘要邮件。
	| 发送由 notification:send-digest 调度命令错峰派发，防止整点大量投递触发邮箱限流。
	*/
	'digest' => [
		'enabled' => true,
		'window_minutes' => 180,
		// 每个调度周期最多认领的用户数（与每分钟调度组合实现错峰）
		'batch_per_tick' => 20,
		// 投递任务随机延迟扩散区间（秒），进一步打散发送时刻
		'spread_seconds' => 300,
		// 认领标记 TTL（分钟），防止重复认领；任务执行失败后超时自动重新认领
		'claim_ttl_minutes' => 15,
		// 每封邮件最多展示的帖子数，超出部分折叠为「以及其他 N 个帖子」
		'entities_per_mail' => 5,
		// 每个帖子展示的互动者上限，超出折叠为「以及其他 N 人」
		'actors_per_entity' => 6,
		// 每个帖子展示的评论条数上限
		'comments_per_entity' => 3,
		// 参与聚合的通知类型
		'types' => [
			'post.reacted',
			'comment.reacted',
			'post.commented',
			'user.followed',
			'user.followed-requested',
			'post.mentioned',
			'comment.mentioned',
			'story.mentioned',
		],
	],
	/*
	|--------------------------------------------------------------------------
	| 营销通知（批量邮件 + 站内通知）
	|--------------------------------------------------------------------------
	| master 开关：MARKETING_NOTIFICATIONS_ENABLED 控制整个营销模块是否可用。
	| 邮件走 QQ/自定义 SMTP，配合 EmailRateLimiter 智能限速：
	|   初始 initial_per_minute 封/分钟，命中限流后惩罚降速至 min_per_minute，
	|   稳定后逐步爬升至 max_per_minute；命中后进入 cooldown 冷却窗口。
	| 站内通知无特殊限流（写库 + 广播，常规流量）。
	*/
	'marketing' => [
		'enabled' => env('MARKETING_NOTIFICATIONS_ENABLED', false),
		// 站内通知通道默认可用（受用户「平台通知」开关约束）
		'in_app_enabled' => env('MARKETING_INAPP_ENABLED', true),
		// App 系统级推送（预留）：需同时配置 services.fcm.server_key，未配置时自动跳过
		'fcm_enabled' => env('MARKETING_FCM_ENABLED', false),
		'email' => [
			'enabled' => env('MARKETING_EMAIL_ENABLED', false),
			'initial_per_minute' => (int) env('MARKETING_EMAIL_INITIAL_PER_MINUTE', 30),
			'min_per_minute' => (int) env('MARKETING_EMAIL_MIN_PER_MINUTE', 15),
			'max_per_minute' => (int) env('MARKETING_EMAIL_MAX_PER_MINUTE', 60),
			// 命中限流后的冷却窗口（秒），期间暂停发送（动态降速）
			'cooldown_seconds' => (int) env('MARKETING_EMAIL_COOLDOWN_SECONDS', 600),
			// 连续成功一定数量后向上调节一次限制
			'raise_after_successes' => (int) env('MARKETING_EMAIL_RAISE_AFTER', 25),
			'raise_step' => 1,
			// 命中限流后降低倍率（半减）
			'penalty_factor' => 0.5,
			// Job 无配额时的延迟释放秒数
			'release_seconds' => 15,
			// 限流错误判定关键字（消息中包含即视为 SMTP 限流）
			'rate_limit_markers' => ['421', '450', '451', '452', 'too many', 'throttl', 'rate limit', 'temporarily'],
		],
		'dispatch' => [
			// 每个调度周期（每分钟）每个活动最多派发的 Job 数（粗粒度削峰，配合限速器精确控速）
			'per_tick' => (int) env('MARKETING_DISPATCH_PER_TICK', 50),
			// 站内通知每 tick 派发上限（无需限速，仅防瞬时洪峰）
			'in_app_per_tick' => (int) env('MARKETING_INAPP_PER_TICK', 200),
		],
		// 权限控制：开启后仅 root 管理员可创建/发送营销活动
		'send_root_only' => env('MARKETING_CAMPAIGN_SEND_ROOT_ONLY', false),
	],
];
