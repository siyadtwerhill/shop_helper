<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopOwner;
use App\Models\User;
use App\Models\Staff;
use App\Models\ProductUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProducts;
use Tests\TestCase;

class SalesControllerTest extends TestCase
{
    use RefreshDatabase, CreatesProducts;

    public function test_sale_above_stock_returns_422(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $product = Product::factory()->for($shop)->create();
        $catalogUnit = \App\Models\Unit::factory()->create();
        $productUnit = \App\Models\ProductUnit::factory()->for($product)->forUnit($catalogUnit)->base()->create();

        $this->giveStock($product, 10);

        // Try to sell 15 units (more than available)
        $response = $this->actingAs($user)->postJson('/api/pos/sales', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'unit_id' => $productUnit->id,
                    'quantity' => '15.0000',
                    'unit_price' => '100.00',
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
    }

    public function test_old_sales_routes_return_405(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $sale = \App\Models\Sale::factory()->for($shop)->create(['status' => 'completed']);

        $this->actingAs($user)->postJson('/api/sales', ['total_amount' => '500.00'])->assertStatus(405);
        $this->actingAs($user)->putJson("/api/sales/{$sale->id}", ['total_amount' => '500.00'])->assertStatus(405);
        $this->actingAs($user)->deleteJson("/api/sales/{$sale->id}")->assertStatus(405);
    }

    public function test_void_restores_stock_of_a_plain_product(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $catalogUnit = \App\Models\Unit::factory()->create();
        $product = Product::factory()->for($shop)->create();
        $productUnit = \App\Models\ProductUnit::factory()->for($product)->forUnit($catalogUnit)->base()->create();

        app(\App\Services\InventoryMovementService::class)->purchase(
            $product,
            $productUnit,
            '10',
            'Initial stock',
            $user->id
        );

        $beforeSale = \App\Models\InventoryMovement::where('product_id', $product->id)->sum('base_quantity');

        $sale = $this->actingAs($user)->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $productUnit->id,
                'quantity' => '3',
                'unit_price' => 1000,
            ]],
        ])->assertCreated()->json();

        $afterSale = \App\Models\InventoryMovement::where('product_id', $product->id)->sum('base_quantity');
        $this->assertEquals('7.0000', $afterSale);

        $this->actingAs($user)->postJson("/api/sales/{$sale['id']}/void", ['reason' => 'wrong item'])->assertOk();

        $afterVoid = \App\Models\InventoryMovement::where('product_id', $product->id)->sum('base_quantity');

        $this->assertEquals($beforeSale, $afterVoid);
        $this->assertSame('voided', \App\Models\Sale::find($sale['id'])->status);
    }

    public function test_voiding_twice_returns_409(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $catalogUnit = \App\Models\Unit::factory()->create();
        $product = Product::factory()->for($shop)->create();
        $productUnit = \App\Models\ProductUnit::factory()->for($product)->forUnit($catalogUnit)->base()->create();

        app(\App\Services\InventoryMovementService::class)->purchase(
            $product,
            $productUnit,
            '10',
            'Initial stock',
            $user->id
        );

        $sale = $this->actingAs($user)->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $productUnit->id,
                'quantity' => '3',
                'unit_price' => 1000,
            ]],
        ])->assertCreated()->json();

        $this->actingAs($user)->postJson("/api/sales/{$sale['id']}/void", ['reason' => 'wrong item'])->assertOk();
        $this->actingAs($user)->postJson("/api/sales/{$sale['id']}/void", ['reason' => 'wrong item'])->assertStatus(409);
    }

    public function test_pending_sale_cannot_be_voided(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $sale = \App\Models\Sale::factory()->for($shop)->create(['status' => 'pending']);

        $this->actingAs($user)->postJson("/api/sales/{$sale->id}/void", ['reason' => 'wrong item'])->assertStatus(422);
    }

    public function test_staff_without_sales_delete_cannot_void(): void
    {
        $shop = ShopOwner::factory()->create();
        $staffUser = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->for($shop)->for($staffUser)->create(['status' => 'active']);
        $shop->user()->associate(User::factory()->create(['role' => 'shop_owner']));
        $shop->save();

        $catalogUnit = \App\Models\Unit::factory()->create();
        $product = Product::factory()->for($shop)->create();
        $productUnit = \App\Models\ProductUnit::factory()->for($product)->forUnit($catalogUnit)->base()->create();

        app(\App\Services\InventoryMovementService::class)->purchase(
            $product,
            $productUnit,
            '10',
            'Initial stock',
            $shop->user->id
        );

        $sale = $this->actingAs($shop->user)->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $productUnit->id,
                'quantity' => '3',
                'unit_price' => 1000,
            ]],
        ])->assertCreated()->json();

        $this->actingAs($staffUser)->postJson("/api/sales/{$sale['id']}/void", ['reason' => 'wrong item'])->assertStatus(403);
    }

    public function test_shop_b_cannot_void_shop_a_sale(): void
    {
        $shopA = ShopOwner::factory()->create();
        $userA = User::factory()->create(['role' => 'shop_owner']);
        $shopA->user()->associate($userA);
        $shopA->save();

        $shopB = ShopOwner::factory()->create();
        $userB = User::factory()->create(['role' => 'shop_owner']);
        $shopB->user()->associate($userB);
        $shopB->save();

        $catalogUnit = \App\Models\Unit::factory()->create();
        $product = Product::factory()->for($shopA)->create();
        $productUnit = \App\Models\ProductUnit::factory()->for($product)->forUnit($catalogUnit)->base()->create();

        app(\App\Services\InventoryMovementService::class)->purchase(
            $product,
            $productUnit,
            '10',
            'Initial stock',
            $userA->id
        );

        $sale = $this->actingAs($userA)->postJson('/api/pos/sales', [
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $productUnit->id,
                'quantity' => '3',
                'unit_price' => 1000,
            ]],
        ])->assertCreated()->json();

        $this->actingAs($userB)->postJson("/api/sales/{$sale['id']}/void", ['reason' => 'wrong item'])->assertStatus(404);
    }
}
