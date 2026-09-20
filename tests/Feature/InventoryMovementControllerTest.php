<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Services\InventoryMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_lists_movements_newest_first(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['current_stock' => '0.0000']);
        $unit = ProductUnit::factory()->for($product)->base()->create();
        app(InventoryMovementService::class)->purchase($product, $unit, 10);
        app(InventoryMovementService::class)->purchase($product, $unit, 5);

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/movements");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame('5.0000', $response->json('data.0.quantity'));
    }

    public function test_manual_adjustment_endpoint_creates_a_movement_and_updates_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['current_stock' => '20.0000']);
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