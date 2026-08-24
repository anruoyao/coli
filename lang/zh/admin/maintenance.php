<?php

return [
    'index_title' => '维护模式（应急）',
    'form' => [
        'enabled' => '启用维护模式',
        'enabled_helper' => '开启后所有用户将被强制下线，App 进入维护提示页，API 请求返回 503。后台仍可访问以随时关闭。',
        'message' => '维护公告',
        'message_placeholder' => '例如：系统正在紧急维护中，预计 2 小时内恢复，感谢理解…',
        'message_helper' => '展示在 App 维护页与 API 返回信息中。留空使用默认文案。',
        'until' => '预计恢复时间',
        'until_helper' => '可选的预计恢复时间，展示给用户。留空表示未知。',
    ],
    'flash' => [
        'enabled' => '维护模式已开启，所有在线用户已被强制下线！',
        'disabled' => '维护模式已关闭，服务已恢复。',
    ],
];