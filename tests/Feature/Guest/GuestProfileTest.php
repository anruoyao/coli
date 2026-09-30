<?php

namespace Tests\Feature\Guest;

use App\Enums\User\UserStatus;

/**
 * AC-4：访客主页脱敏。
 */
class GuestProfileTest extends GuestTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableGuestMode();
    }

    public function test_profile_returns_whitelist_fields(): void
    {
        $user = $this->makeUser();

        $this->guestGet('profile/'.$user->username)->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', $user->username)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'username', 'caption', 'bio',
                    'avatar_url', 'cover_url',
                    'followers_count' => ['raw', 'formatted'],
                    'following_count' => ['raw', 'formatted'],
                    'publications_count' => ['raw', 'formatted'],
                    'meta' => [
                        'permissions' => [
                            'can_follow', 'can_message', 'can_edit',
                            'can_view_followers', 'can_view_followings',
                        ],
                    ],
                ],
            ]);
    }

    public function test_profile_does_not_leak_sensitive_keys(): void
    {
        $user = $this->makeUser();

        $content = $this->guestGet('profile/'.$user->username)
            ->getContent();

        // 原始 JSON 中不出现敏感字段名
        $this->assertStringNotContainsString('"email"', $content);
        $this->assertStringNotContainsString('"phone"', $content);
        $this->assertStringNotContainsString('website', $content);
        $this->assertStringNotContainsString('social_links', $content);
        $this->assertStringNotContainsString('last_active', $content);
        $this->assertStringNotContainsString('city', $content);
    }

    public function test_blocked_user_profile_returns_404(): void
    {
        $user = $this->makeUser(['status' => UserStatus::BLOCKED->value]);

        $this->guestGet('profile/'.$user->username)->assertNotFound();
    }

    public function test_profile_posts_respect_visibility_boundary(): void
    {
        $user = $this->makeUser();
        $visible = $this->makePost($user, ['content' => 'PROFILE_VISIBLE']);
        $this->makePost($user, [
            'content' => 'PROFILE_SENSITIVE',
            'is_sensitive' => true,
        ]);

        $contents = array_column(
            $this->guestGet('profile/'.$user->username.'/posts')->json('data'),
            'content'
        );

        $this->assertContains('PROFILE_VISIBLE', $contents);
        $this->assertNotContains('PROFILE_SENSITIVE', $contents);
    }

    public function test_followers_endpoint_does_not_exist(): void
    {
        $user = $this->makeUser();

        $this->guestGet('profile/'.$user->username.'/followers')->assertNotFound();
        $this->guestGet('profile/'.$user->username.'/followings')->assertNotFound();
    }
}
