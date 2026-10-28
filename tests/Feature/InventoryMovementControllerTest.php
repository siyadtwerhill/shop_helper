<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Branch;
use App\Models\InventoryMovement;
use App\Models\ProductUnit;
use App\Models\Role;
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
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shopOwner->user()->associate($user);
        $shopOwner->save();
        
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
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shopOwner->user()->associate($user);
        $shopOwner->save();
        
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

    public function test_branch_head_only_sees_own_branch_movements_and_adjustments_are_attributed(): void
    {
        $shop = ShopOwner::factory()->create();
        $owner = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($owner);
        $shop->save();
        $branchA = Branch::factory()->create(['shop_owner_id' => $shop->id]);
        $branchB = Branch::factory()->create(['shop_owner_id' => $shop->id]);
        $head = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->for($shop)->for($head)->create([
            'branch_id' => $branchA->id,
            'status' => 'active',
        ]);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($shop->id);
        $role = Role::create(['name' => 'Branch Head', 'guard_name' => 'web', 'shop_owner_id' => $shop->id]);
        foreach (['inventory.stock.view', 'inventory.stock.edit'] as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $head->assignRole($role);
        $branchA->update(['head_staff_id' => $staff->id]);
        $registrar->setPermissionsTeamId(null);

        $product = Product::factory()->for($shop)->forBranch($branchB->id)->create(['current_stock' => '20.0000']);
        $unit = ProductUnit::factory()->for($product)->base()->create();
        foreach ([$branchA, $branchB] as $branch) {
            InventoryMovement::create([
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'unit_id' => $unit->id,
                'quantity' => '1.0000',
                'base_quantity' => '1.0000',
                'movement_type' => 'purchase',
            ]);
        }

        $this->actingAs($head)->getJson("/api/products/{$product->id}/movements")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', $branchA->id);

        $adjustment = $this->actingAs($head)->postJson("/api/products/{$product->id}/movements", [
            'unit_id' => $unit->id,
            'quantity' => '-2',
            'note' => 'Branch stocktake',
        ])->assertCreated()->json();

        $this->assertSame($branchA->id, $adjustment['branch_id']);
        $this->assertSame('18.0000', $product->refresh()->current_stock);
        $this->actingAs($owner)->getJson("/api/products/{$product->id}/movements")
            ->assertOk()->assertJsonCount(3, 'data');

        $otherShop = ShopOwner::factory()->create();
        $otherProduct = Product::factory()->for($otherShop)->create();
        $this->actingAs($head)->getJson("/api/products/{$otherProduct->id}/movements")->assertNotFound();
    }
}
