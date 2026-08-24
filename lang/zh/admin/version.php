<?php

return [
    'index_title' => '版本管理',
    'list_helper' => '管理各平台移动应用版本。上线的版本会通过 /api/system/version/check 接口推送给 App 客户端。',
    'actions' => [
        'create' => '新建版本',
        'back' => '返回列表',
    ],
    'table' => [
        'version' => '版本代号',
        'platform' => '平台',
        'download' => '下载链接',
        'forced' => '强制更新',
        'status' => '状态',
        'released_at' => '发布时间',
        'online' => '已上线',
        'offline' => '已下线',
        'immediate' => '立即',
        'empty' => '暂无版本记录，点击右上角「新建版本」创建第一条版本。',
    ],
    'form' => [
        'code' => '版本代号',
        'code_helper' => '语义化版本号，例如 2.1.0。同一平台下不可重复。',
        'platform' => '目标平台',
        'platform_helper' => '该版本推送到的客户端平台。',
        'download_url' => '安装包下载链接',
        'download_url_helper' => 'App 内点击「立即更新」时打开的外部链接（APK / App Store / TestFlight 等）。',
        'notes' => '更新公告',
        'notes_placeholder' => '每行一条更新说明，App 内会按行展示…',
        'notes_helper' => 'App 更新弹窗中展示的公告内容，支持多行。',
        'released_at' => '发布时间',
        'released_at_helper' => '留空表示立即生效；填写未来时间可实现定时发布。',
        'is_forced' => '强制更新',
        'is_forced_helper' => '开启后，低于该版本的用户在 App 内将被强制要求升级（不可忽略）。',
        'is_active' => '启用（上线）',
        'is_active_helper' => '仅上线的版本参与版本检测；关闭后可下线历史版本。',
    ],
    'prompts' => [
        'delete' => [
            'content' => '确定要删除该版本记录吗？此操作不可撤销。',
        ],
    ],
    'flash' => [
        'created' => '版本已创建。',
        'updated' => '版本已更新。',
        'deleted' => '版本已删除。',
    ],
];