<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserBlock;
use App\Enums\User\UserStatus;
use App\Actions\User\BlockUserAction;
use App\Actions\User\UnblockUserAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BlockUserTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->targetUser = User::factory()->create(['status' => UserStatus::ACTIVE]);
    }

    public function test_user_can_block_another_user(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/blocks/block/user', ['id' => $this->targetUser->id]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.relationship.block.blocking', true);

        $this->assertDatabaseHas('user_blocks', [
            'user_id' => $this->user->id,
            'blocked_user_id' => $this->targetUser->id,
        ]);
    }

    public function test_user_can_unblock_a_blocked_user(): void
    {
        UserBlock::create([
            'user_id' => $this->user->id,
            'blocked_user_id' => $this->targetUser->id,
            'blocked_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/blocks/unblock/user', ['id' => $this->targetUser->id]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.relationship.block.blocking', false);

        $this->assertDatabaseMissing('user_blocks', [
            'user_id' => $this->user->id,
            'blocked_user_id' => $this->targetUser->id,
        ]);
    }

    public function test_blocking_already_blocked_user_returns_success(): void
    {
        UserBlock::create([
            'user_id' => $this->user->id,
            'blocked_user_id' => $this->targetUser->id,
            'blocked_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/blocks/block/user', ['id' => $this->targetUser->id]);

        $response->assertStatus(200)
            ->assertJsonPath('data.relationship.block.blocking', true);
    }

    public function test_unblocking_not_blocked_user_returns_success(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/blocks/unblock/user', ['id' => $this->targetUser->id]);

        $response->assertStatus(200)
            ->assertJsonPath('data.relationship.block.blocking', false);
    }

    public function test_blocking_nonexistent_user_returns_404(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/blocks/block/user', ['id' => 99999]);

        $response->assertStatus(404);
    }

    public function test_can_get_blocked_users_list(): void
    {
        UserBlock::create([
            'user_id' => $this->user->id,
            'blocked_user_id' => $this->targetUser->id,
            'blocked_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/blocks/blocked/users');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }

    public function test_blocked_users_list_is_empty_for_new_user(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/blocks/blocked/users');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_user_cannot_block(): void
    {
        $response = $this->postJson('/api/blocks/block/user', ['id' => $this->targetUser->id]);

        $response->assertStatus(401);
    }

    public function test_has_blocked_returns_true_when_blocked(): void
    {
        (new BlockUserAction(user: $this->user, targetUser: $this->targetUser))->execute();

        $this->assertTrue($this->user->hasBlocked($this->targetUser));
    }

    public function test_has_blocked_returns_false_when_not_blocked(): void
    {
        $this->assertFalse($this->user->hasBlocked($this->targetUser));
    }

    public function test_is_blocked_by_returns_true_when_blocked(): void
    {
        (new BlockUserAction(user: $this->targetUser, targetUser: $this->user))->execute();

        $this->assertTrue($this->user->isBlockedBy($this->targetUser));
    }

    public function test_unblock_action_removes_block_record(): void
    {
        (new BlockUserAction(user: $this->user, targetUser: $this->targetUser))->execute();

        $result = (new UnblockUserAction(user: $this->user, targetUser: $this->targetUser))->execute();

        $this->assertTrue($result);
        $this->assertFalse($this->user->hasBlocked($this->targetUser));
    }

    public function test_cannot_block_self(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/blocks/block/user', ['id' => $this->user->id]);

        $response->assertStatus(422);
    }

    public function test_blocked_users_cursor_pagination_works(): void
    {
        $users = User::factory()->count(5)->create(['status' => UserStatus::ACTIVE]);

        foreach ($users as $u) {
            UserBlock::create([
                'user_id' => $this->user->id,
                'blocked_user_id' => $u->id,
                'blocked_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/blocks/blocked/users');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');

        $lastItem = collect($response->json('data'))->last();

        $nextResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/blocks/blocked/users?cursor=' . $lastItem['cursor_id']);

        $nextResponse->assertStatus(200);
    }

    public function test_blocked_user_profile_is_not_owner(): void
    {
        UserBlock::create([
            'user_id' => $this->user->id,
            'blocked_user_id' => $this->targetUser->id,
            'blocked_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/profile/profile?id=' . $this->targetUser->username);

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.is_owner', false)
            ->assertJsonPath('data.meta.permissions.can_follow', false)
            ->assertJsonPath('data.meta.permissions.can_block', true)
            ->assertJsonPath('data.meta.relationship.block.blocking', true);
    }

    public function test_blocking_user_profile_is_not_owner(): void
    {
        UserBlock::create([
            'user_id' => $this->targetUser->id,
            'blocked_user_id' => $this->user->id,
            'blocked_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/profile/profile?id=' . $this->targetUser->username);

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.is_owner', false)
            ->assertJsonPath('data.meta.permissions.can_follow', false)
            ->assertJsonPath('data.meta.permissions.can_block', true)
            ->assertJsonPath('data.meta.relationship.block.blocked_by', true);
    }

    public function test_own_profile_shows_is_owner_true(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/profile/profile?id=' . $this->user->username);

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.is_owner', true)
            ->assertJsonPath('data.meta.permissions.can_follow', false)
            ->assertJsonPath('data.meta.permissions.can_block', false)
            ->assertJsonPath('data.meta.permissions.can_mention', false);
    }

    public function test_unblocked_user_can_still_be_followed(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/profile/profile?id=' . $this->targetUser->username);

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.is_owner', false)
            ->assertJsonPath('data.meta.permissions.can_follow', true)
            ->assertJsonPath('data.meta.permissions.can_block', true);
    }

    public function test_cannot_follow_blocked_user(): void
    {
        UserBlock::create([
            'user_id' => $this->user->id,
            'blocked_user_id' => $this->targetUser->id,
            'blocked_at' => now(),
        ]);

        $this->assertFalse($this->user->canFollow($this->targetUser));
    }

    public function test_cannot_follow_user_who_blocked_you(): void
    {
        UserBlock::create([
            'user_id' => $this->targetUser->id,
            'blocked_user_id' => $this->user->id,
            'blocked_at' => now(),
        ]);

        $this->assertFalse($this->user->canFollow($this->targetUser));
    }
}
