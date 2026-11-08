<?php

namespace Tests\Feature\Security;

use App\Enums\User\FollowStatus;
use App\Enums\User\UserStatus;
use App\Models\Follow;
use App\Services\Relations\FollowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class FollowAcceptanceIdempotencyTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    public function test_repeated_acceptance_increments_counters_once(): void
    {
        $follower = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $following = $this->makeUser(['status' => UserStatus::ACTIVE]);
        Follow::create([
            'follower_id' => $follower->id,
            'following_id' => $following->id,
            'status' => FollowStatus::REQUESTED,
        ]);
        $service = new FollowService($follower, $following);

        $service->accept();
        $service->accept();

        $this->assertSame(FollowStatus::FOLLOWING->value, Follow::where('follower_id', $follower->id)->where('following_id', $following->id)->firstOrFail()->status);
        $this->assertSame(1, (int) $following->fresh()->followers_count);
        $this->assertSame(1, (int) $follower->fresh()->following_count);
    }
}
