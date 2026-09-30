<?php

namespace Tests\Feature\Guest;

/**
 * AC-9：爬虫始终获得 SEO HTML（即使访客功能开启）。
 */
class GuestCrawlerSeoTest extends GuestTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableGuestMode();
    }

    public function test_search_engine_crawler_receives_seo_html(): void
    {
        $user = $this->makeUser();
        $post = $this->makePost($user);

        $this->get('/publication/'.$post->hash_id, [
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ])->assertOk()->assertViewIs('apps.seo.index')->assertSee('og:title', false);
    }

    public function test_social_crawler_receives_seo_html(): void
    {
        $user = $this->makeUser();

        $this->get('/@'.$user->username, [
            'User-Agent' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
        ])->assertOk()->assertViewIs('apps.seo.index');
    }

    public function test_real_browser_receives_spa_shell_when_enabled(): void
    {
        $this->get('/publication/1', [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        ])->assertOk()->assertViewIs('desktop::index');
    }
}
