<?php

namespace Tests\Unit\Feedback;

use App\Models\Censor;
use Tests\TestCase;
use App\Enums\CensorLevel;
use Illuminate\Support\Facades\Cache;
use App\Services\Feedback\ReportContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * 举报补充说明净化单元测试（XSS 剥离 / 控制字符 / 长度截断 / 敏感词拦截）。
 */
class ReportContentSanitizerTest extends TestCase
{
    use RefreshDatabase;

    private ReportContentSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new ReportContentSanitizer();

        Cache::forget('censor_banned_words');
    }

    public function test_strips_html_tags_for_xss_protection(): void
    {
        $this->assertSame(
            'Hello World',
            $this->sanitizer->sanitize('<script>alert(1)</script>Hello <b>World</b>')
        );
    }

    public function test_strips_attribute_based_injection(): void
    {
        $this->assertSame(
            'click',
            $this->sanitizer->sanitize('<img src=x onerror=alert(1)>click</img>')
        );
    }

    public function test_removes_control_characters(): void
    {
        $this->assertSame('abc', $this->sanitizer->sanitize("a\x00\x1Fb\x7Fc"));
    }

    public function test_compresses_whitespace_and_truncates_length(): void
    {
        $this->assertSame('a b c', $this->sanitizer->sanitize("  a   b\t\tc  "));
        $this->assertSame(500, mb_strlen($this->sanitizer->sanitize(str_repeat('字', 600))));
    }

    public function test_blank_comment_becomes_null(): void
    {
        $this->assertNull($this->sanitizer->sanitize(null));
        $this->assertNull($this->sanitizer->sanitize('   '));
        $this->assertNull($this->sanitizer->sanitize('<p></p>'));
    }

    public function test_rejects_banned_words_from_database(): void
    {
        Censor::create(['word' => 'badword', 'level' => CensorLevel::BANNED]);

        $this->assertTrue($this->sanitizer->containsBannedWords('这句话里有 badword 出现'));
        $this->assertFalse($this->sanitizer->containsBannedWords('这句话很干净'));
    }

    public function test_banned_word_matching_is_case_insensitive(): void
    {
        Censor::create(['word' => 'BadWord', 'level' => CensorLevel::BANNED]);

        $this->assertTrue($this->sanitizer->containsBannedWords('包含 badword 的小写'));
    }

    public function test_warning_level_words_are_not_rejected(): void
    {
        Censor::create(['word' => 'softword', 'level' => CensorLevel::WARNING]);

        $this->assertFalse($this->sanitizer->containsBannedWords('包含 softword'));
    }
}
