<?php

namespace Tests\Feature\Security;

use App\Enums\Category\CategoryStatus;
use App\Enums\Category\CategoryType;
use App\Enums\Product\ProductApproval;
use App\Enums\Product\ProductStatus;
use App\Enums\User\UserStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class UnapprovedListingAuthorizationTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('X-App-Key', 'clbPK-8f3k2m9xq4w7v1t6a5s0d2n8h4j6y1c');
    }

    public function test_unapproved_products_are_visible_to_owner_and_root_only(): void
    {
        $owner = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $other = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $root = $this->makeUser(['status' => UserStatus::ACTIVE, 'role' => 'root']);
        $category = Category::create([
            'localization' => ['en' => 'Security test'],
            'categorizable_type' => CategoryType::PRODUCT,
            'status' => CategoryStatus::ACTIVE,
            'depth' => 1,
        ]);
        $product = Product::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Pending product',
            'status' => ProductStatus::ACTIVE,
            'approval' => ProductApproval::PENDING,
            'stock_quantity' => 3,
            'price' => '10',
            'currency' => 'USD',
        ]);
        $path = '/api/marketplace/products/' . $product->hash_id;

        $this->actingAs($owner, 'sanctum')->getJson($path)->assertOk();
        $this->actingAs($other, 'sanctum')->getJson($path)->assertNotFound();
        $this->actingAs($root, 'sanctum')->getJson($path)->assertOk();
    }
}
