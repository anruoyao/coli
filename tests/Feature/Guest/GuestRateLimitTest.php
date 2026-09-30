<?php

namespace Tests\Feature\Guest;

/**
 * AC-8：访客限流（429 + Retry-After）。
 */
class GuestRateLimitTest extends GuestTestCase
{
    public function test_guest_requests_are_rate_limited_per_device_id(): void
    {
        $this->enableGuestMode();

        config(['security.guest.rate_per_minute' => 2]);

        // 固定 device_id cookie，确保命中同一限流桶
        $context = $this->withUnencryptedCookie('device_id', 'guest-rate-test-device');

        $context->getJson('/api/guest/v1/feed')->assertOk();
        $context->getJson('/api/guest/v1/feed')->assertOk();

        $response = $this->withUnencryptedCookie('device_id', 'guest-rate-test-device')
            ->getJson('/api/guest/v1/feed');

        $response->assertStatus(429);
        $this->assertNotEmpty($response->headers->get('Retry-After'));
    }
}
