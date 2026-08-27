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
];
