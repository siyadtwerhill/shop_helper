<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use App\Services\InventoryMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventoryMovementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Permission::firstOrCreate(['name' => 'inventory.stock.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'inventory.stock.edit', 'guard_name' => 'web']);
    }

    public function test_activity_log_lists_movements_newest_first(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->create(['user_id' => $user->id, 'shop_owner_id' => $shopOwner->id]);
        $user->setRelation('staff', $staff);
        
        app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);
        $user->givePermissionTo('inventory.stock.view');
        
        $product = Product::factory()->for($shopOwner)->create(['current_stock' => '0.0000']);
        $unit = ProductUnit::factory()->forProduct($product)->base()->create();
        app(InventoryMovementService::class)->purchase($product, $unit, 10);
        app(InventoryMovementService::class)->purchase($product, $unit, 5);

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/movements");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame('5.0000', $response->json('data.0.quantity'));
    }

    public function test_manual_adjustment_endpoint_creates_a_movement_and_updates_stock(): void
    {
        $shopOwner = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->create(['user_id' => $user->id, 'shop_owner_id' => $shopOwner->id]);
        $user->setRelation('staff', $staff);
        
        app(PermissionRegistrar::class)->setPermissionsTeamId($shopOwner->id);
        $user->givePermissionTo('inventory.stock.edit');
        
        $product = Product::factory()->for($shopOwner)->create(['current_stock' => '20.0000']);
        $unit = ProductUnit::factory()->for($product)->base()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/movements", [
            'unit_id' => $unit->id,
            'quantity' => -3,
            'note' => 'Damaged during transport',
        ]);

        $response->assertCreated();
        $this->assertSame('17.0000', $product->refresh()->current_stock);
    }
}