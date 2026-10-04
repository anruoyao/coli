<?php

return [
    'index_title' => 'API 限流监控',
    'list_helper' => '展示三道限流防线的 429 命中事件：分类限流（throttle:api.*）、风控动作（abuse:*）与全局 IP 闸门。每 30 秒自动刷新。',
    'search_placeholder' => '搜索标识符 / 路径 / IP…',
    'stats' => [
        'total' => '今日 429 命中',
        'user' => '用户维度',
        'ip' => 'IP 维度',
        'gate' => '全局闸门命中',
    ],
    'tabs' => [
        'all' => '全部',
        'user' => '用户',
        'ip' => 'IP',
        'device' => '设备',
        'global' => '全局',
        'today' => '今日',
        '24h' => '24 小时',
        '7d' => '7 天',
    ],
    'filter' => [
        'all_categories' => '全部分类',
    ],
    'table' => [
        'time' => '时间',
        'dimension' => '维度',
        'identifier' => '标识符',
        'category' => '分类（额度 / 窗口）',
        'path' => '方法与路径',
        'ip' => 'IP',
    ],
];
