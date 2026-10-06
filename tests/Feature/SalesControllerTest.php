<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ShopOwner;
use App\Models\User;
use App\Models\Staff;
use App\Models\ProductUnit;
use App\Models\InventoryMovement;
use App\Exceptions\InsufficientStockException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_above_stock_returns_422(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $product = Product::factory()->for($shop)->create();
        $catalogUnit = \App\Models\Unit::factory()->create();
        $productUnit = \App\Models\ProductUnit::factory()->for($product)->forUnit($catalogUnit)->base()->create();

        // Add 10 units of stock
        InventoryMovement::factory()->for($product)->create([
            'unit_id' => $catalogUnit->id,
            'quantity' => '10.0000',
            'movement_type' => 'addition',
        ]);

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
}
