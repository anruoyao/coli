<?php

return [
	'group' => [
		'avatar' => 'assets/avatars/default-avatar.png',
		'invite_expire_days' => 7
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
	]
];