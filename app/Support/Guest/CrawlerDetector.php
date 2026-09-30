<?php

namespace App\Support\Guest;

/**
 * 爬虫 / 社交分享抓取器识别（基于 UA 关键词，不依赖开关）。
 *
 * 用途：即使访客功能开启，无 JS 执行能力的爬虫与分享抓取器仍应获得
 * 服务端渲染的 SEO HTML（meta + JSON-LD + 正文快照），保证收录与链接卡片。
 */
class CrawlerDetector
{
    /**
     * UA 关键词（小写匹配）。覆盖主流搜索引擎爬虫、社交与 IM 分享抓取器、
     * 链接预览服务。普通浏览器（Chrome/Safari/Firefox/Edge/微信内置浏览器真人）
     * 不会命中。
     */
    private const SIGNATURES = [
        'bot', 'crawler', 'spider', 'slurp',
        'facebookexternalhit', 'facebot',
        'twitterbot', 'telegrambot', 'whatsapp',
        'embedly', 'quora link preview', 'pinterest', 'redditbot',
        'linkedinbot', 'googlebot', 'bingbot', 'baiduspider',
        'yandexbot', 'duckduckbot', 'applebot', 'semrushbot',
        'mj12bot', 'ahrefsbot', 'dotbot', 'rogerbot',
        'sogou web spider', 'exabot', 'ia_archiver',
        'skypeuripreview', 'slackbot', 'discordbot',
    ];

    public static function isCrawler(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            // UA 为空时按爬虫处理：空 UA 多为脚本/探测流量，给 SEO HTML 更安全。
            return true;
        }

        $userAgent = mb_strtolower($userAgent);

        foreach (self::SIGNATURES as $signature) {
            if (str_contains($userAgent, $signature)) {
                return true;
            }
        }

        return false;
    }
}
