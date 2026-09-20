<?php

namespace Tests\Unit\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ShopOwner;
use App\Services\InventoryMovementService;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryMovementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryMovementService(new UnitConversionService());
        ShopOwner::factory()->create();
    }

    public function test_purchase_increases_current_stock(): void
    {
        $product = Product::factory()->create(['current_stock' => '0.0000']);
        $unit = ProductUnit::factory()->for($product)->base()->create();

        $this->service->purchase($product, $unit, 50);

        $this->assertSame('50.0000', $product->refresh()->current_stock);
    }

    /** Matches the spec's own rice worked example: 100 KG, sell 2 KG, then sell 500 G. */
    public function test_selling_in_a_non_base_unit_matches_spec_rice_example(): void
    {
        $product = Product::factory()->create(['current_stock' => '100.0000']);
        $kg = ProductUnit::factory()->for($product)->base()->create();
        $gram = ProductUnit::factory()->for($product)->create(['conversion_factor' => '0.0010']);

        $this->service->sale($product, $kg, 2);
        $this->assertSame('98.0000', $product->refresh()->current_stock);

        $this->service->sale($product, $gram, 500);
        $this->assertSame('97.5000', $product->refresh()->current_stock);
    }

    public function test_sale_throws_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create(['current_stock' => '1.0000']);
        $kg = ProductUnit::factory()->for($product)->base()->create();

        $this->expectException(InsufficientStockException::class);
        $this->service->sale($product, $kg, 5);
    }

    public function test_opening_stock_sets_the_initial_balance(): void
    {
        $product = Product::factory()->create(['current_stock' => '0.0000']);
        $kg = ProductUnit::factory()->for($product)->base()->create();

        $movement = $this->service->openingStock($product, $kg, 200);

        $this->assertSame('200.0000', $product->refresh()->current_stock);
        $this->assertSame('opening_stock', $movement->movement_type);
    }

    public function test_adjustment_supports_a_negative_delta(): void
    {
        $product = Product::factory()->create(['current_stock' => '50.0000']);
        $kg = ProductUnit::factory()->for($product)->base()->create();

        $movement = $this->service->adjust($product, $kg, -5, note: 'Stocktake shrinkage');

        $this->assertSame('45.0000', $product->refresh()->current_stock);
        $this->assertSame('-5.0000', $movement->base_quantity);
        $this->assertSame('Stocktake shrinkage', $movement->note);
    }

    public function test_movement_stores_its_reference_type_and_id(): void
    {
        $product = Product::factory()->create(['current_stock' => '10.0000']);
        $kg = ProductUnit::factory()->for($product)->base()->create();

        $movement = $this->service->purchase($product, $kg, 5, referenceType: 'purchase_order', referenceId: 77);

        $this->assertSame('purchase_order', $movement->reference_type);
        $this->assertSame(77, $movement->reference_id);
    }
}