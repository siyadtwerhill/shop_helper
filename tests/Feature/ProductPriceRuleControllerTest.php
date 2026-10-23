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

    public function test_creating_a_price_rule_requires_products_pricing_create_permission(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->create(['user_id' => $user->id, 'shop_owner_id' => $shopOwner->id]);
        $user->setRelation('staff', $staff);
        
        app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);
        
        $product = Product::factory()->for($shopOwner)->create();
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();

        $this->actingAs($user)->postJson("/api/products/{$product->id}/price-rules", [
            'unit_id' => $unit->id,
            'min_quantity' => 10,
            'max_quantity' => 49,
            'price' => 4500,
        ])->assertForbidden();
    }

    public function test_user_with_permission_can_create_a_tier(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->create(['user_id' => $user->id, 'shop_owner_id' => $shopOwner->id]);
        $user->setRelation('staff', $staff);
        
        app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);
        $user->givePermissionTo('products.pricing.create');
        
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