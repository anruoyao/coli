<?php

namespace Tests\Feature\Marketing;

use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Marketing\Concerns\CreatesUsers;

class NotificationPlatformSettingsTest extends TestCase
{
    use RefreshDatabase, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.app_key.enabled' => false]);
    }

    private function settingsKeys(): array
    {
        return ['direct_messages', 'reactions', 'comments', 'shared_posts', 'followers', 'follow_request', 'mentions'];
    }

    public function test_push_settings_response_contains_platform_notifications_default_true(): void
    {
        $user = $this->makeUserWithSettings();
        Sanctum::actingAs($user);

        $this->getJson('/api/settings/notifications/push/settings')
            ->assertOk()
            ->assertJsonPath('data.platform_notifications', true);

        $this->getJson('/api/settings/notifications/email/settings')
            ->assertOk()
            ->assertJsonPath('data.platform_notifications', true);
    }

    public function test_updating_push_settings_persists_platform_notifications(): void
    {
        $user = $this->makeUserWithSettings();
        Sanctum::actingAs($user);

        $payload = array_merge(Arr::flip($this->settingsKeys()), ['platform_notifications' => false]);

        $this->putJson('/api/settings/notification/push/update', $payload)
            ->assertOk();

        $this->getJson('/api/settings/notifications/push/settings')
            ->assertOk()
            ->assertJsonPath('data.platform_notifications', false);

        // 邮件通道不受推送通道更新影响
        $this->getJson('/api/settings/notifications/email/settings')
            ->assertOk()
            ->assertJsonPath('data.platform_notifications', true);
    }

    public function test_updating_email_settings_persists_platform_notifications(): void
    {
        $user = $this->makeUserWithSettings();
        Sanctum::actingAs($user);

        $payload = array_merge(Arr::flip($this->settingsKeys()), ['platform_notifications' => false]);

        $this->putJson('/api/settings/notification/email/update', $payload)
            ->assertOk();

        $this->getJson('/api/settings/notifications/email/settings')
            ->assertOk()
            ->assertJsonPath('data.platform_notifications', false);
    }

    public function test_platform_notifications_is_independent_from_other_toggles(): void
    {
        $user = $this->makeUserWithSettings();
        Sanctum::actingAs($user);

        $payload = array_fill_keys($this->settingsKeys(), false);

        $this->putJson('/api/settings/notification/push/update', $payload + ['platform_notifications' => true])
            ->assertOk();

        $this->getJson('/api/settings/notifications/push/settings')
            ->assertOk()
            ->assertJsonPath('data.mentions', false)
            ->assertJsonPath('data.platform_notifications', true);
    }
}