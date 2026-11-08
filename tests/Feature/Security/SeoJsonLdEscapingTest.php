<?php

namespace Tests\Feature\Security;

use App\Services\Seo\SeoMeta;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SeoJsonLdEscapingTest extends TestCase
{
    public function test_json_ld_cannot_close_its_script_element(): void
    {
        $payload = [
            '@type' => 'Article',
            'description' => '</script><script>alert(1)</script> & "quoted"',
        ];
        $seo = new SeoMeta(
            title: 'SEO test',
            description: 'SEO test',
            canonical: 'https://example.test/post',
            type: 'profile',
            jsonLd: $payload,
            bodyView: 'apps.seo.parts.profile',
            data: [
                'user' => (object) [
                    'avatar_url' => null,
                    'name' => 'Test User',
                    'username' => 'test',
                    'bio' => '',
                ],
                'posts' => new Collection(),
            ],
        );

        $html = view('apps.seo.index', ['seo' => $seo])->render();
        preg_match('/<script type="application\\/ld\\+json">(.*?)<\\/script>/s', $html, $matches);

        $this->assertSame(1, preg_match_all('/<script\\b/i', $html));
        $this->assertArrayHasKey(1, $matches);
        $this->assertStringContainsString('\\u003C/script\\u003E', $matches[1]);
        $this->assertSame($payload, json_decode($matches[1], true));
    }
}
