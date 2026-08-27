<?php

return [
    'index_title' => '在线用户',
    'list_helper' => '实时反映当前正在使用平台的用户会话（Web 浏览器 / Android / iOS App）。每 30 秒自动刷新。',
    'search_placeholder' => '搜索昵称 / 用户名 / 邮箱…',
    'stats' => [
        'total' => '当前在线',
        'web' => 'Web',
        'android' => 'Android',
        'ios' => 'iOS',
    ],
    'tabs' => [
        'all' => '全部',
        'web' => 'Web',
        'android' => 'Android',
        'ios' => 'iOS',
        'live' => '实时在线',
        'analytics' => '数据分析',
    ],
    'table' => [
        'user' => '用户',
        'platform' => '平台',
        'origin' => 'IP / 位置',
        'online_since' => '在线时长',
        'last_seen' => '最后活跃',
    ],
    'actions' => [
        'refresh' => '刷新',
        'view' => '查看',
        'export' => '导出 CSV',
    ],
    'prompts' => [
        'kick' => [
            'content' => '确定将该会话强制下线吗？该用户的 App 端登录凭证将被全部吊销（网页端会话保留，但不再计入在线）。',
        ],
    ],
    'analytics' => [
        'dau' => '日活跃 (24h)',
        'wau' => '周活跃 (7d)',
        'mau' => '月活跃 (30d)',
        'today_peak' => '今日在线峰值',
        'hourly_trend' => '24 小时在线趋势',
        'hourly_hint' => '按小时快照（presence:aggregate 每小时聚合），分平台在线会话数。',
        'daily_trend' => '近 7 日在线总量',
        'daily_hint' => '每日在线会话总量（求和）与单日峰值（小时桶内最大在线）。',
        'empty_hint' => '暂无快照数据。快照由系统每小时自动聚合生成，稍后刷新即可看到趋势。',
        'series_total' => '全部',
        'series_daily_total' => '当日总量',
        'series_daily_peak' => '单日峰值',
    ],
    'export' => [
        'headers' => [
            'user' => '用户',
            'platform' => '平台',
            'platform_detail' => '平台详情',
            'ip' => 'IP',
            'location' => '位置',
        ],
    ],
];