<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductPriceRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Permission::firstOrCreate(['name' => 'products.pricing.create', 'guard_name' => 'web']);
    }

    public function test_user_with_permission_can_create_a_tier(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shopOwner->user()->associate($user);
        $shopOwner->save();
        
        $product = Product::factory()->for($shopOwner)->create();
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/price-rules", [
            'unit_id' => $unit->id,
            'min_quantity' => 10,
            'max_quantity' => 49,
            'price' => 4500,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('product_price_rules', ['product_id' => $product->id, 'price' => 4500]);
    }
}