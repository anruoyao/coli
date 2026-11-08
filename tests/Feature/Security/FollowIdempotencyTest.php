<?php

namespace Tests\Feature\Security;

use App\Enums\User\PrivacyPermit;
use App\Enums\User\UserStatus;
use App\Models\Follow;
use App\Services\Relations\FollowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class FollowIdempotencyTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    public function test_repeated_follow_requests_create_one_relationship_and_one_counter_increment(): void
    {
        $follower = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $following = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $following->permitSettings()->create(['followers' => PrivacyPermit::ALL]);
        $service = new FollowService($follower, $following);

        $first = $service->follow();
        $second = $service->follow();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Follow::where('follower_id', $follower->id)->where('following_id', $following->id)->count());
        $this->assertSame(1, (int) $following->fresh()->followers_count);
        $this->assertSame(1, (int) $follower->fresh()->following_count);
    }
}
