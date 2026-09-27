<?php

namespace Tests\Unit\Marketing;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Notifications\DatabaseNotification;
use App\Notifications\User\System\PlatformMarketingNotification;

class PlatformMarketingNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.marketing.in_app_enabled' => true,
            'notifications.broadcast.enabled' => false,
        ]);
    }

    private function user(): User
    {
        $user = new User(['id' => 1]);

        return $user;
    }

    public function test_database_channel_is_always_present_when_enabled(): void
    {
        $notification = new PlatformMarketingNotification('Title', 'Body', null);

        $this->assertSame(['database'], $notification->via($this->user()));
    }

    public function test_broadcast_channel_added_when_enabled(): void
    {
        config(['notifications.broadcast.enabled' => true]);

        $notification = new PlatformMarketingNotification('Title', 'Body', null);

        $this->assertSame(['database', 'broadcast'], $notification->via($this->user()));
    }

    public function test_empty_channels_when_in_app_disabled(): void
    {
        config(['notifications.marketing.in_app_enabled' => false]);

        $notification = new PlatformMarketingNotification('Title', 'Body', null);

        $this->assertSame([], $notification->via($this->user()));
    }

    public function test_database_payload_shape_is_consistent(): void
    {
        $notification = new PlatformMarketingNotification('Summer Event', 'Join us!', 'https://example.com/event');

        $data = $notification->toDatabase();

        $this->assertArrayHasKey('entity', $data);
        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('metadata', $data);

        $this->assertSame('Summer Event', $data['entity']['title']);
        $this->assertSame('Join us!', $data['entity']['content']);
        $this->assertSame('https://example.com/event', $data['entity']['destination_url']);
        $this->assertSame('marketing', $data['message_group']);
        $this->assertSame('platform_notice', $data['message_key']);

        // 系统 actor：非真实用户，前端用于展示官方标识
        $this->assertSame('system', $data['actor']['type']);
        $this->assertTrue($data['metadata']['marketing']);

        // 消息文案通过 lang 渲染（通知中心通用 message）
        $this->assertStringContainsString('Summer Event', $data['message_params']['title']);
    }
}