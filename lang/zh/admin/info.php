<?php

return [
	'you_are_admin' => '您已以管理员身份登录。🛡️',
    'env_edit_notice' => [
		'title' => '如何编辑？',
		'line_one' => '这些设置从 <code>.env</code> 文件（位于 ColibriPlus 安装的根目录）管理，无法从管理后台更改。',
		'line_two' => '要更新它们，请直接编辑 <code>.env</code> 文件，然后点击重置缓存按钮即可。',
		'env_privacy' => '⚠️ 请勿与任何人分享您的 .env 文件。它包含应用的所有机密信息。'
	],
	'payment_preview' => [
		'title' => '支付对象',
		'line_one' => '支付对象是一个管理对象，用于表示用户进行的支付。',
		'line_two' => '它包含支付参考 ID 以及与支付相关的其他数据。',
		'line_three' => '在支付完成或过期之前，请避免编辑或删除此对象。'
	],
	'language_edit_notice' => [
		'title' => '如何编辑？',
		'line_one' => '所有语言文本都以 <code>.php</code> 和 <code>.json</code> 格式存储在本地文件中。',
		'line_two' => '要编辑文本，请直接编辑 <code>.php</code> 或 <code>.json</code> 文件，并遵循 <a class="text-brand-900 underline" href=":documentation_url" target="_blank">文档</a>。'
	],
	'translation_notice' => [
		'title' => '需要手动翻译！',
		'line_one' => '所有翻译文件将以英文（en - 永久区域设置）为基准进行复制。',
		'line_two' => '请注意，新添加的语言默认不会被翻译。',
		'line_three' => '您必须手动更新翻译文件以反映正确的语言。',
		'line_four' => '👉 按照文档中的翻译指南进行操作。'
	],
	'currency_notice' => [
		'title' => '法定货币 💰',
		'line_one' => '货币是应用中用于业务内容（如职位、商品等）的法定货币。',
		'line_two' => '请避免删除您的用户正在使用的货币。'
	],
	'ban_notice' => [
		'title' => '封禁内容 🚫',
		'line_one' => '封禁内容是已被禁止在应用中使用的内容。',
		'line_two' => '您可以选择封禁多种类型的内容，如 IP、邮箱、电话、用户名、邮箱域名等。',
		'line_three' => '如果设置了过期日期，封禁内容将在到期后自动解除。'
	],
	'round_robin_notice' => [
		'title' => '轮询存储 🔄',
		'line_one' => 'ColibriPlus 采用轮询存储系统，支持 S3 和 FTP 作为后端选项。',
		'line_two' => '您可以添加任意数量的 S3 或 FTP 存储账户——无论是来自 AWS、DigitalOcean、Vultr 还是任何其他支持这些协议的提供商。',
		'line_three' => '配置完成后，ColibriPlus 将自动以轮询方式在可用存储账户之间分发文件，帮助您无缝平衡存储使用。'
	],
	'laravel_notice' => [
		'title' => 'Laravel 生态系统 🚀',
		'line_one' => 'ColibriPlus 基于 Laravel :laravel_version 构建。<a href="https://www.laravel.com" target="_blank" class="text-brand-900">了解更多</a>',
		'line_two' => '这意味着您可以自由使用任何您想要的 Laravel 生态系统工具、包和服务。'
	],
	'category_notice' => [
		'title' => '分类',
		'line_one' => '创建实体分类（例如商品或职位）以满足您的需求。添加翻译，使分类名称与每个用户选择的语言匹配。',
	],
	'page_edit_notice' => [
		'title' => '静态页面',
		'line_one' => '静态页面用于显示与您的项目相关的法律或信息内容。例如：Cookie 政策、隐私政策、服务条款、关于您的公司等类似页面。',
		'line_two' => '您可以为每种支持的语言添加翻译，以便以用户首选语言显示信息。',
	],
	'chat_notice' => [
		'title' => '私信聊天',
		'line_one' => '聊天是两个或更多用户之间的私人对话。在此版本中，不支持从管理后台查看消息内容。',
		'line_two' => '如有必要，您可以删除整个聊天。',
	],
	'smtp_solutions' => [
		'title' => '没有 SMTP 服务器？',
		'line_one' => '最简单的方法是使用第三方免费 SMTP 服务，例如 Google SMTP、SendGrid 或 Amazon SES。',
		'line_two' => '您也可以使用本地 SMTP 服务器，例如 Postfix 或 Exim。',
        'line_three' => '查看文档以获取更多信息。',
	],
	'ffmpeg_notice' => [
		'title' => ':app_name 中 FFMPEG 的用途是什么？',
		'line_one' => ':app_name 使用 FFMPEG 在将用户视频上传到平台之前对其进行压缩，以优化平台性能并减少存储使用。',
        'line_two' => '它会将所有上传的视频转换为平台默认格式（MP4）。',
	],
    'acquiring_notice' => [
        'title' => '什么是支付收款？',
        'line_one' => '支付收款允许用户充值其应用内钱包，可用于应用内购买和服务。',
        'line_two' => '要激活支付收款，请选择支付提供商并在下方配置其 API 凭证。',
        'line_three' => 'API 密钥由您的支付提供商直接颁发。',
    ],
    'social_login_notice' => [
        'title' => '什么是社交登录？',
        'line_one' => '社交登录允许用户使用其社交媒体账号登录您的应用。',
        'line_two' => '要激活社交登录，请选择社交媒体提供商并在下方配置其 API 凭证。',
        'line_three' => '凭证由您的社交媒体提供商直接颁发。',
    ],
    'wallet_notice' => [
        'title' => '什么是钱包？',
        'line_one' => '钱包让您的用户拥有应用内余额，用于支付平台服务和应用内购买。',
        'line_two' => '您可以启用任意支付提供商，允许用户向其钱包充值。',
    ],
];
