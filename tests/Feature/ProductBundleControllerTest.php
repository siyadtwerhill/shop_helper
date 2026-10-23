<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductBundleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Set team context to null for permission creation
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Permission::firstOrCreate(['name' => 'products.bundles.create', 'guard_name' => 'web']);
    }

    public function test_creating_a_bundle_with_components(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = $shopOwner->staff()->create(['user_id' => $user->id]);
        
        // Set team context for this shop
        app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);
        $user->givePermissionTo('products.bundles.create');
        
        $bundleProduct = Product::factory()->for($shopOwner)->create();
        $coffee = Product::factory()->for($shopOwner)->create();
        $coffeeUnit = ProductUnit::factory()->for($coffee)->base()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$bundleProduct->id}/bundle", [
            'bundle_price' => 7500,
            'items' => [
                ['component_product_id' => $coffee->id, 'unit_id' => $coffeeUnit->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('product_bundles', ['product_id' => $bundleProduct->id, 'bundle_price' => 7500]);
        $this->assertDatabaseHas('product_bundle_items', ['component_product_id' => $coffee->id]);
    }

    public function test_a_product_cannot_be_turned_into_a_bundle_twice(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = $shopOwner->staff()->create(['user_id' => $user->id]);
        
        app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);
        $user->givePermissionTo('products.bundles.create');
        
        $bundleProduct = Product::factory()->for($shopOwner)->create();
        $coffee = Product::factory()->for($shopOwner)->create();
        $coffeeUnit = ProductUnit::factory()->for($coffee)->base()->create();

        $payload = [
            'bundle_price' => 7500,
            'items' => [['component_product_id' => $coffee->id, 'unit_id' => $coffeeUnit->id, 'quantity' => 1]],
        ];

        $this->actingAs($user)->postJson("/api/products/{$bundleProduct->id}/bundle", $payload)->assertCreated();
        $this->actingAs($user)->postJson("/api/products/{$bundleProduct->id}/bundle", $payload)->assertStatus(409);
    }

    public function test_a_bundle_cannot_contain_itself_as_a_component(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = $shopOwner->staff()->create(['user_id' => $user->id]);
        
        app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);
        $user->givePermissionTo('products.bundles.create');
        
        $bundleProduct = Product::factory()->for($shopOwner)->create();
        $unit = ProductUnit::factory()->for($bundleProduct)->base()->create();

        $this->actingAs($user)->postJson("/api/products/{$bundleProduct->id}/bundle", [
            'bundle_price' => 7500,
            'items' => [['component_product_id' => $bundleProduct->id, 'unit_id' => $unit->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }
}
