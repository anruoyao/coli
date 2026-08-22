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
];
