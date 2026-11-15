<?php

namespace Tests\Unit\Security;

use App\Services\Security\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_removes_active_markup_and_unsafe_urls(): void
    {
        $html = '<p onclick="run()">Safe<script>alert(1)</script></p>'
            .'<img src="x" onerror="run()">'
            .'<a href="javascript:run()">unsafe</a>'
            .'<a href="https://example.com" target="_blank">safe link</a>';

        $result = (new HtmlSanitizer())->sanitize($html);

        $this->assertStringContainsString('<p>Safe</p>', $result);
        $this->assertStringContainsString('<img src="x">', $result);
        $this->assertStringContainsString('<a>unsafe</a>', $result);
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringContainsString('rel="noopener noreferrer"', $result);
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_it_preserves_safe_content_and_escapes_blade_syntax(): void
    {
        $result = (new HtmlSanitizer())->sanitize('<font><strong>你好</strong></font> {{ 7 * 7 }} @include("secret")');

        $this->assertStringContainsString('<strong>你好</strong>', $result);
        $this->assertStringContainsString('&#123;&#123; 7 * 7 &#125;&#125;', $result);
        $this->assertStringContainsString('&#64;include', $result);
    }
}
