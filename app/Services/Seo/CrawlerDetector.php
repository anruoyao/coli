<?php

namespace App\Services\Seo;

class CrawlerDetector
{
    private static array $patterns = [
        'googlebot',
        'google-structured-data-testing-tool',
        'bingbot',
        'msnbot',
        'slurp',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'facebot',
        'facebookexternalhit',
        'twitterbot',
        'whatsapp',
        'telegrambot',
        'discordbot',
        'slackbot',
        'linkedinbot',
        'pinterest',
        'redditbot',
        'rogerbot',
        'dotbot',
        'semrushbot',
        'ahrefsbot',
        'mj12bot',
        'applebot',
        'swiftbot',
    ];

    public function isCrawler(string $userAgent): bool
    {
        $agent = mb_strtolower($userAgent);

        foreach (self::$patterns as $pattern) {
            if (str_contains($agent, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
