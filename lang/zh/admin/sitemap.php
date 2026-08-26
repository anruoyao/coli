<?php

return [
    'index_title' => 'Sitemap / SEO',

    'overview' => [
        'title' => '状态总览',
        'helper' => '当前 Sitemap 的生成状态与统计。搜索引擎（Google / Bing / 百度等）会通过 robots.txt 与 Sitemap 文件发现并抓取站点 URL。',
        'status' => 'Sitemap 状态',
        'on' => '已开启',
        'off' => '已关闭',
        'last_generated' => '最近生成时间',
        'never' => '从未生成',
        'total_urls' => 'URL 总数',
        'chunks' => '分片数',
    ],

    'sections' => [
        'basic' => '基础设置',
        'inclusion' => '收录内容设置',
        'inclusion_helper' => '开关各类内容的收录，并配置每类的数量上限、更新频率与优先级。',
        'exclusion' => '排除规则',
        'robots' => 'robots.txt',
        'actions' => '维护操作',
        'actions_helper' => '立即重新生成 Sitemap，或向搜索引擎推送通知新 Sitemap 已就绪。',
    ],

    'form' => [
        'enabled' => '启用 Sitemap',
        'enabled_helper' => '总开关。关闭后 /sitemap.xml 返回 404，并从 robots.txt 移除 Sitemap 行。',
        'seo_head_enabled' => '启用公开 SEO 页面',
        'seo_head_enabled_helper' => '对未登录访客在公开的用户主页 / 帖子 / 职位 / 商品 / 故事 URL 上输出服务端渲染的 SEO 页面（meta + JSON-LD + 正文快照），解决 SPA 动态内容无法被爬虫收录的问题。',
        'per_page' => '每分片 URL 上限',
        'per_page_helper' => '每个分片文件中的最大 URL 数（sitemap 协议上限为 50,000）。',
        'cache_ttl' => '缓存时长（分钟）',
        'cache_ttl_helper' => '生成的 Sitemap 缓存的时长，过期后自动重建。',
        'limit' => '数量上限（0 = 不限）',
        'changefreq' => '更新频率',
        'priority' => '优先级（0.0 – 1.0）',
        'excluded_paths' => '排除路径',
        'excluded_paths_helper' => '每行一条。按 URL 路径前缀匹配（如 /settings）；如需正则，请用斜杠包裹：/^\/private\//。',
        'robots_sitemap_line' => '在 robots.txt 中注入 Sitemap 行',
        'robots_sitemap_line_helper' => '开启后，将当前 Sitemap 地址自动追加到 public/robots.txt。',
        'robots_custom' => '自定义 robots.txt 内容（可选）',
        'robots_custom_helper' => '填写后将作为 robots.txt 正文（开启注入时仍会追加 Sitemap 行）。留空使用默认内容。',
        'robots_preview' => '当前 public/robots.txt',
    ],

    'types' => [
        'static' => '静态页面',
        'users' => '用户主页',
        'posts' => '动态帖子',
        'stories' => '故事',
        'jobs' => '职位',
        'products' => '商品',
    ],

    'actions' => [
        'regenerate' => '立即重新生成',
        'ping_google' => '推送 Google',
        'ping_bing' => '推送 Bing',
        'google_pinged' => 'Google 最近推送',
        'bing_pinged' => 'Bing 最近推送',
    ],

    'engines' => [
        'google' => 'Google',
        'bing' => 'Bing',
    ],

    'flash' => [
        'saved' => 'Sitemap 设置已保存：缓存已重建，robots.txt 已更新。',
        'regenerated' => 'Sitemap 已重新生成，共收录 :count 条 URL。',
        'ping_success' => '已成功通知 :engine。',
        'ping_failed' => '通知 :engine 失败，请稍后重试。',
    ],
];