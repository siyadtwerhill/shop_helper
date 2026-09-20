<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class UnitConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private UnitConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UnitConversionService();
    }

    public function test_converts_bag_quantity_to_base_kg(): void
    {
        $product = Product::factory()->create();
        $kg = Unit::factory()->create(['name' => 'Kilogram', 'symbol' => 'KG', 'type' => 'weight']);
        $bag = Unit::factory()->create(['name' => 'Bag', 'symbol' => 'အိတ်', 'type' => 'packaging']);

        $baseUnit = ProductUnit::factory()->for($product)->for($kg, 'unit')->base()->create();
        $bagUnit = ProductUnit::factory()->for($product)->for($bag, 'unit')->create(['conversion_factor' => '20.0000']);

        $this->assertSame('100.0000', $this->service->toBaseQuantity($bagUnit, 5));
        $this->assertSame($baseUnit->id, $this->service->baseUnitFor($product)->id);
    }

    public function test_converts_grams_back_from_base_and_matches_spec_example(): void
    {
        $product = Product::factory()->create();
        $kg = Unit::factory()->create(['name' => 'Kilogram', 'symbol' => 'KG']);
        $g = Unit::factory()->create(['name' => 'Gram', 'symbol' => 'G']);

        ProductUnit::factory()->for($product)->for($kg, 'unit')->base()->create();
        $gramUnit = ProductUnit::factory()->for($product)->for($g, 'unit')->create(['conversion_factor' => '0.0010']);

        $deducted = $this->service->toBaseQuantity($gramUnit, 500);
        $this->assertSame('0.5000', $deducted);
    }

    public function test_from_base_quantity_throws_on_zero_conversion_factor(): void
    {
        $product = Product::factory()->create();
        $unit = Unit::factory()->create();
        $productUnit = ProductUnit::factory()->for($product)->for($unit, 'unit')->create(['conversion_factor' => '0.0000']);

        $this->expectException(InvalidArgumentException::class);
        $this->service->fromBaseQuantity($productUnit, '10.0000');
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $product = Product::factory()->create();
        $unit = Unit::factory()->create();
        $productUnit = ProductUnit::factory()->for($product)->for($unit, 'unit')->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->toBaseQuantity($productUnit, -1);
    }

    public function test_base_unit_for_throws_when_product_has_no_base_unit(): void
    {
        $product = Product::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->service->baseUnitFor($product);
    }

    public function test_convert_between_two_sibling_units_round_trips_through_base(): void
    {
        $product = Product::factory()->create();
        $kg = Unit::factory()->create(['name' => 'Kilogram']);
        $bag = Unit::factory()->create(['name' => 'Bag']);

        ProductUnit::factory()->for($product)->for($kg, 'unit')->base()->create();
        $bagUnit = ProductUnit::factory()->for($product)->for($bag, 'unit')->create(['conversion_factor' => '20.0000']);
        $kgUnit = $product->units()->where('is_base', true)->first();

        $this->assertSame('40.0000', $this->service->convertBetween($bagUnit, $kgUnit, 2));
        $this->assertSame('2.0000', $this->service->convertBetween($kgUnit, $bagUnit, 40));
    }

    public function test_convert_between_rejects_units_from_different_products(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $unitA = ProductUnit::factory()->for($productA)->create();
        $unitB = ProductUnit::factory()->for($productB)->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->convertBetween($unitA, $unitB, 1);
    }

    public function test_set_base_unit_clears_previous_base_and_syncs_product_cache_column(): void
    {
        $product = Product::factory()->create();
        $unitA = ProductUnit::factory()->for($product)->base()->create();
        $unitB = ProductUnit::factory()->for($product)->create(['is_base' => false]);

        $this->service->setBaseUnit($product, $unitB);

        $this->assertFalse($unitA->refresh()->is_base);
        $this->assertTrue($unitB->refresh()->is_base);
        $this->assertSame('1.0000', $unitB->conversion_factor);
        $this->assertSame($unitB->id, $product->refresh()->base_unit_id);
    }
}