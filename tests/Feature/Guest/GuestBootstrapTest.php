<?php

namespace Tests\Feature\Guest;

/**
 * AC-1 / AC-7：访客 bootstrap 公开可用性与开关行为。
 */
class GuestBootstrapTest extends GuestTestCase
{
    public function test_bootstrap_returns_403_when_guest_mode_disabled(): void
    {
        $this->guestGet('bootstrap')->assertStatus(403);
    }

    public function test_anonymous_bootstrap_structure_when_enabled(): void
    {
        $this->enableGuestMode();

        $response = $this->guestGet('bootstrap')->assertOk();

        $data = $response->json('data');

        $this->assertFalse($data['auth']['status']);
        $this->assertNull($data['auth']['user']);

        $this->assertTrue($data['guest']['enabled']);

        // 能力矩阵：写/互动能力全部 false
        $capabilities = $data['guest']['capabilities'];

        $this->assertFalse($capabilities['post']['create']);
        $this->assertFalse($capabilities['comment']['create']);
        $this->assertFalse($capabilities['poll']['vote']);
        $this->assertFalse($capabilities['follow']);
        $this->assertFalse($capabilities['bookmark']);
        $this->assertFalse($capabilities['messenger']);
        $this->assertFalse($capabilities['stories']);
        $this->assertFalse($capabilities['wallet']);
        $this->assertFalse($capabilities['settings']);

        $this->assertNotEmpty($data['guest']['visible_nav']);
    }

    public function test_bootstrap_returns_authenticated_user_with_session(): void
    {
        $this->enableGuestMode();

        $user = $this->makeUser();

        $this->actingAs($user);

        $response = $this->guestGet('bootstrap')->assertOk();

        $data = $response->json('data');

        $this->assertTrue($data['auth']['status']);
        $this->assertSame($user->id, $data['auth']['user']['id']);
        $this->assertArrayHasKey('meta', $data['auth']['user']);
    }
}
