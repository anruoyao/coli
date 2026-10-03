<?php

return [
	'group' => [
		'avatar' => 'assets/avatars/default-avatar.png',
		'invite_expire_days' => 7
	],
    'validation' => [
        'message' => [
            'media_type' => [
                'types' => ['image', 'video', 'audio'],
            ],
            'media' => [
                'mimes' => join(',', [
                    'mp4',
                    'avi',
                    'mpeg',
                    'mov',
                    'webm',
                    'gif',
                    'jpeg',
                    'png',
                    'jpg',
                    'webp',
                    'heic',
                    'heif',
                    'heif-sequence',
                    'heic-sequence',
                    'mp3',
                    'wav',
                    'm4a',
                    'ogg',
                    'aac',
                    'opus',
                ]),
                'mimetypes' => join(',', [
                    'video/mp4',
                    'video/avi',
                    'video/mpeg',
                    'video/quicktime',
                    'video/webm',
                    'image/gif',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/heic',
                    'image/heif',
                    'image/heif-sequence',
                    'image/heic-sequence',
                    'audio/mpeg',
                    'audio/wav',
                    'audio/x-wav',
                    'audio/mp4',
                    'audio/aac',
                    'audio/ogg',
                    'audio/opus',
                    'audio/webm',
                ]),
                'max' => '512000' // 512MB
            ],
        ]
    ],
	'message' => [
		'validation' => [
			'content' => [
				'min' => 1,
				'max' => 2200
			],
		]
	],
	'colors' => [
		'#C7508B',
		'#D67722',
		'#CC5049',
		'#309eba',
		'#40a920',
		'#955cdb'
	],
	'sounds' => [
		'active_chat_message_received' => 'assets/sounds/chats/active-chat-message-received.mp3',
		'background_chat_message_received' => 'assets/sounds/chats/background-chat-message-received.mp3',
		'chat_message_sent' => 'assets/sounds/chats/chat-message-sent.mp3',
    ],
    'enable_video_compression' => true,

    // 聊天媒体回收：消息被会话中「所有当前参与者」本地删除（隐藏）且超过宽限期后，
    // 由 chats:reclaim-media 定时任务回收图片/视频/语音文件（本地盘与 S3 均适用）。
    'media_reclamation' => [
        'enabled' => env('CHAT_MEDIA_RECLAMATION_ENABLED', true),
        'grace_days' => env('CHAT_MEDIA_RECLAMATION_GRACE_DAYS', 7),
        'batch_size' => env('CHAT_MEDIA_RECLAMATION_BATCH_SIZE', 500),
    ],
];
