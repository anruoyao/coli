<?php

namespace Tests\Feature\Guest;

/**
 * AC-6：访客功能上线后，现有需认证 API 的行为不变（未登录 401）。
 */
class ExistingApiIsolationTest extends GuestTestCase
{
    public function test_existing_authenticated_endpoints_still_require_auth(): void
    {
        $this->getJson('/api/timeline/feed')->assertStatus(401);
        $this->getJson('/api/profile/profile?id=someone')->assertStatus(401);
        $this->getJson('/api/bootstrap/bootstrap')->assertStatus(401);
        $this->getJson('/api/messenger/chats')->assertStatus(401);
    }

    public function test_existing_endpoints_still_401_even_when_guest_mode_enabled(): void
    {
        $this->enableGuestMode();

        $this->getJson('/api/timeline/feed')->assertStatus(401);
    }
}
