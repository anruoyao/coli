<?php

return [
	'you_are_admin' => '您以管理员身份登录。🛡️',
    'env_edit_notice' => [
		'title' => '如何编辑？',
		'line_one' => '这些设置通过 <code>.env</code> 文件管理（位于 ColibriPlus 安装根目录），无法从管理面板更改。',
		'line_two' => '要更新它们，请直接编辑 <code>.env</code> 文件，然后点击重置缓存按钮。',
		'env_privacy' => '⚠️ 请不要与任何人共享您的 .env 文件。它包含所有应用程序的机密信息。'
	],
	'payment_preview' => [
		'title' => '支付对象',
		'line_one' => '支付对象是一个管理对象，用于表示用户进行的支付。',
		'line_two' => '它包含支付参考 ID 和其他与支付相关的数据。',
		'line_three' => '请避免在支付完成或过期之前编辑或删除此对象。'
	],
	'language_edit_notice' => [
		'title' => '如何编辑？',
		'line_one' => '所有语言文本存储在本地 <code>.php</code> 和 <code>.json</code> 格式的文件中。',
		'line_two' => '要编辑文本，请直接编辑 <code>.php</code> 或 <code>.json</code> 文件，遵循 <a class="text-brand-900 underline" href=":documentation_url" target="_blank">文档</a>。'
	],
	'translation_notice' => [
		'title' => '需要手动翻译！',
		'line_one' => '所有翻译文件将从英语（en - 永久语言环境）复制作为基础。',
		'line_two' => '请注意，新添加的语言默认不会被翻译。',
		'line_three' => '您必须手动更新翻译文件以反映正确的语言。',
		'line_four' => '👉 请按照文档中的翻译指南进行操作。'
	],
	'currency_notice' => [
		'title' => '法定货币 💰',
		'line_one' => '货币是在应用程序中用于商业内容（如工作、产品等）的法定货币。',
		'line_two' => '请避免删除用户正在使用的货币。'
	],
	'ban_notice' => [
		'title' => '已封禁内容 🚫',
		'line_one' => '已封禁内容是从应用程序中封禁的内容。',
		'line_two' => '您可以选择封禁多种类型的内容，如 IP、邮箱、电话、用户名、邮箱域名等。',
		'line_three' => '如果设置了过期日期，已封禁内容将在过期日期后自动移除。'
	],
	'round_robin_notice' => [
		'title' => '轮询存储 🔄',
		'line_one' => 'ColibriPlus 具有支持 S3 和 FTP 作为后端选项的轮询存储系统。',
		'line_two' => '您可以添加任意数量的 S3 或 FTP 存储账户——无论是来自 AWS、DigitalOcean、Vultr 还是任何支持这些协议的其他提供商。',
		'line_three' => '配置后，ColibriPlus 将自动在可用存储账户之间分配文件，以轮询方式帮助您无缝平衡存储使用。'
	],
	'laravel_notice' => [
		'title' => 'Laravel 生态系统 🚀',
		'line_one' => 'ColibriPlus 构建于 Laravel :laravel_version 之上。<a href="https://www.laravel.com" target="_blank" class="text-brand-900">了解更多</a>',
		'line_two' => '这意味着您可以自由使用任何 Laravel 生态系统工具、包和服务。'
	],
	'category_notice' => [
		'title' => '分类',
		'line_one' => '创建符合您需求的实体分类（例如产品或职位）。添加翻译以便分类名称与每个用户选择的语言匹配。',
	],
	'page_edit_notice' => [
		'title' => '静态页面',
		'line_one' => '静态页面用于显示与您的项目相关的法律或信息内容。例如：Cookie 政策、隐私政策、服务条款、关于您的公司等页面。',
		'line_two' => '您可以为每种支持的语言添加翻译，以便信息以用户首选的语言显示。'
	],
	'chat_notice' => [
		'title' => '私信',
		'line_one' => '聊天是两个或更多用户之间的私人对话。在当前版本中，管理面板不支持查看消息内容。',
		'line_two' => '如有必要，您可以删除整个聊天。'
	]
];
