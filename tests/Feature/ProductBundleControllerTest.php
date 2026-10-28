<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Blueprint;
use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\Unit;
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
        Unit::factory()->system()->create(['name' => 'Dozen', 'symbol' => 'DZ', 'type' => 'quantity']);
    }

    public function test_creating_a_bundle_with_components(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shopOwner->user()->associate($user);
        $shopOwner->save();
        
        $bundleProduct = $this->bundleProduct($shopOwner);
        ProductUnit::factory()->forProduct($bundleProduct)->base()->create();
        $coffee = Product::factory()->for($shopOwner)->create();
        $coffee->forceFill(['current_stock' => 10])->save();
        $coffeeUnit = ProductUnit::factory()->forProduct($coffee)->base()->create();

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
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shopOwner->user()->associate($user);
        $shopOwner->save();
        
        $bundleProduct = $this->bundleProduct($shopOwner);
        ProductUnit::factory()->forProduct($bundleProduct)->base()->create();
        $coffee = Product::factory()->for($shopOwner)->create();
        $coffee->forceFill(['current_stock' => 10])->save();
        $coffeeUnit = ProductUnit::factory()->forProduct($coffee)->base()->create();

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
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shopOwner->user()->associate($user);
        $shopOwner->save();
        
        $bundleProduct = $this->bundleProduct($shopOwner);
        $unit = ProductUnit::factory()->for($bundleProduct)->base()->create();

        $this->actingAs($user)->postJson("/api/products/{$bundleProduct->id}/bundle", [
            'bundle_price' => 7500,
            'items' => [['component_product_id' => $bundleProduct->id, 'unit_id' => $unit->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    private function bundleProduct(ShopOwner $shopOwner): Product
    {
        $blueprint = Blueprint::factory()->create([
            'shop_owner_id' => $shopOwner->id,
            'capabilities' => ['variants' => false, 'bundles' => true, 'decimal_quantities' => true],
        ]);

        return Product::factory()->for($shopOwner)->withBlueprint($blueprint)->create();
    }
}
