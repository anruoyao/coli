<?php

return [
    'index_title' => '移动应用设置',
    'form' => [
        'service_worker_content' => 'Service Worker 代码',
        'service_worker_content_helper' => '如果您不确定自己在做什么，请勿编辑此代码。',
        'pwa_icons' => 'PWA 应用图标',
        'android_link' => '安卓应用链接',
        'manifest_content' => 'Manifest JSON',
        'manifest_content_helper' => '如果您已启用 PWA，可以在此处添加 manifest 内容。请勿编辑图标部分，它会根据上传的图标自动生成。',
        'android_link_helper' => '如果您有安卓应用，可以在此处添加链接。留空以隐藏链接。',
        'ios_link' => 'iOS 应用链接',
        'ios_link_helper' => '如果您有 iOS 应用，可以在此处添加链接。留空以隐藏链接。',
        'pwa_enabled' => '启用 PWA',
        'pwa_enabled_helper' => 'PWA 已包含在平台中。您可以启用它以允许用户在其设备上安装应用。',
    ],
    'validation' => [
        'pwa_icon_not_found' => '未找到 PWA 图标。',
    ],
    'callouts' => [
        'pwa_icons' => [
            'title' => '上传 PWA 应用图标',
            'caption' => '逐个上传您的应用图标。每个文件必须是 PNG 图片，并且文件名必须与其尺寸完全一致。例如：192x192.png、512x512.png 等。',
        ],
    ],
    'prompts' => [
        'delete_pwa_icon' => [
            'content' => '确定要删除此图标吗？',
        ],
    ],
];
