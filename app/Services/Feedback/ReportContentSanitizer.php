<?php

namespace App\Services\Feedback;

use App\Models\Censor;
use Illuminate\Support\Facades\Cache;

/**
 * 举报补充说明内容净化。
 *
 * - XSS 防护：strip_tags 剥离全部 HTML/Script 标签并移除控制字符
 * - 敏感词过滤：命中后台「敏感词库」（Censor）时拒绝提交（仅过滤，不自动封禁举报人）
 */
class ReportContentSanitizer
{
    public const MAX_LENGTH = 500;

    /**
     * 净化举报补充说明：剥标签、去控制字符、压缩空白、截断长度。
     */
    public function sanitize(?string $comment): ?string
    {
        if($comment === null) {
            return null;
        }

        // 1. XSS 防护：剥离 HTML 标签（含 script/style 注入）
        $text = strip_tags($comment);

        // 2. 移除控制字符（除换行与制表符）
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        // 3. 压缩连续空白
        $text = trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');

        // 4. 长度截断
        $text = mb_substr($text, 0, self::MAX_LENGTH);

        return $text === '' ? null : $text;
    }

    /**
     * 是否命中敏感词库（用于拒绝提交，而非封禁）。
     */
    public function containsBannedWords(string $text): bool
    {
        if(trim($text) === '') {
            return false;
        }

        foreach($this->bannedWords() as $word) {
            $word = trim((string) $word);

            if($word !== '' && mb_stripos($text, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 复用 CensorService 的敏感词缓存键；缓存缺失时直接查库。
     */
    private function bannedWords(): array
    {
        $words = Cache::get('censor_banned_words');

        if(is_array($words) && $words !== []) {
            return $words;
        }

        return Censor::banned()->get()->pluck('word')->toArray();
    }
}
