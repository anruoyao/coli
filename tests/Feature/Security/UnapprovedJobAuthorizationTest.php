<?php

namespace Tests\Feature\Security;

use App\Enums\Category\CategoryStatus;
use App\Enums\Category\CategoryType;
use App\Enums\Job\JobApproval;
use App\Enums\Job\JobStatus;
use App\Enums\User\UserStatus;
use App\Models\Category;
use App\Models\JobListing;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class UnapprovedJobAuthorizationTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('X-App-Key', 'clbPK-8f3k2m9xq4w7v1t6a5s0d2n8h4j6y1c');
    }

    public function test_unapproved_jobs_are_visible_to_owner_and_root_only(): void
    {
        $owner = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $other = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $root = $this->makeUser(['status' => UserStatus::ACTIVE, 'role' => 'root']);
        $category = Category::create([
            'localization' => ['en' => 'Security test'],
            'categorizable_type' => CategoryType::JOB,
            'status' => CategoryStatus::ACTIVE,
            'depth' => 1,
        ]);
        $job = JobListing::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Pending job',
            'status' => JobStatus::ACTIVE,
            'approval' => JobApproval::PENDING,
            'income' => '100',
            'currency' => 'USD',
        ]);
        $path = '/api/jobs/jobs/' . $job->hash_id;

        $this->actingAs($owner, 'sanctum')->getJson($path)->assertOk();
        $this->actingAs($other, 'sanctum')->getJson($path)->assertNotFound();
        $this->actingAs($root, 'sanctum')->getJson($path)->assertOk();
    }
}
