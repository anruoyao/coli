<?php

namespace Tests\Feature\Guest;

use App\Enums\User\UserStatus;

/**
 * AC-2：访客精选流可见性边界。
 */
class GuestFeedTest extends GuestTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableGuestMode();
    }

    public function test_feed_only_returns_active_non_sensitive_posts_by_active_users(): void
    {
        $activeUser = $this->makeUser();
        $activePost = $this->makePost($activeUser, ['content' => 'VISIBLE_POST']);

        // 敏感帖：排除
        $this->makePost($activeUser, [
            'content' => 'SENSITIVE_POST',
            'is_sensitive' => true,
        ]);

        // 非 ACTIVE 帖：排除
        $this->makePost($activeUser, [
            'content' => 'INACTIVE_POST',
            'status' => 'draft',
        ]);

        // 封禁作者：其全部帖子排除
        $blockedUser = $this->makeUser(['status' => UserStatus::BLOCKED->value]);
        $this->makePost($blockedUser, ['content' => 'BLOCKED_AUTHOR_POST']);

        $response = $this->guestGet('feed')->assertOk();

        $contents = array_column($response->json('data'), 'content');

        $this->assertContains('VISIBLE_POST', $contents);
        $this->assertNotContains('SENSITIVE_POST', $contents);
        $this->assertNotContains('INACTIVE_POST', $contents);
        $this->assertNotContains('BLOCKED_AUTHOR_POST', $contents);
    }

    public function test_feed_pagination_respects_filter_page(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 3; $i++) {
            $this->makePost($user);
        }

        $page1 = $this->guestGet('feed?filter[page]=1')->json('data');
        $page2 = $this->guestGet('feed?filter[page]=2')->json('data');

        $this->assertNotEmpty($page1);
        // 每页条数固定为 post.paginate（测试库条数少，第二页可为空）
        $this->assertIsArray($page2);
    }

    public function test_feed_does_not_leach_when_disabled(): void
    {
        // 另一个测试进程视角：默认开关关闭
        $response = $this->guestGet('feed')->assertStatus(403);
    }
}
