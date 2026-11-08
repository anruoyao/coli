<?php

namespace Tests\Feature\Security;

use App\Enums\Chat\ChatType;
use App\Enums\User\UserStatus;
use App\Models\Chat;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroupUpdateAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_non_owner_cannot_update_group_data_or_avatar(): void
    {
        config(['security.app_key.enabled' => false]);
        $owner = User::findOrFail(3);
        $attacker = User::findOrFail(4);
        $this->assertSame(UserStatus::ACTIVE, $attacker->status);

        $chat = Chat::create([
            'chat_id' => (string) Str::uuid(),
            'type' => ChatType::GROUP,
            'created_at' => now(),
        ]);
        $group = Group::create([
            'chat_id' => $chat->id,
            'user_id' => $owner->id,
            'name' => 'Protected group',
            'description' => 'Original description',
            'avatar' => 'original-avatar.jpg',
        ]);
        $originalActivity = $chat->last_activity;

        $this->actingAs($attacker, 'sanctum')
            ->withHeader('X-App-Key', (string) config('app.api_key'))
            ->postJson('/api/messenger/groups/update', [
                'chat_id' => $chat->chat_id,
                'name' => 'Attacker changed name',
                'visibility' => true,
                'description' => 'Attacker changed description',
            ])
            ->assertForbidden();

        $this->assertSame('Protected group', $group->fresh()->name);
        $this->assertSame('Original description', $group->fresh()->description);
        $this->assertEquals($originalActivity, $chat->fresh()->last_activity);

        $this->actingAs($attacker, 'sanctum')
            ->withHeader('X-App-Key', (string) config('app.api_key'))
            ->postJson('/api/messenger/groups/avatar/update', [
                'chat_id' => $chat->chat_id,
                'avatar_file' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertForbidden();

        $this->assertSame('original-avatar.jpg', $group->fresh()->avatar);
    }
}
