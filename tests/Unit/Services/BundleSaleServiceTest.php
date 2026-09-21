<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductUnit;
use App\Services\BundleSaleService;
use App\Services\InventoryMovementService;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleSaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private BundleSaleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BundleSaleService(new InventoryMovementService(new UnitConversionService()));
    }

    /** Spec §11's own example: Breakfast Set = 1 Coffee + 1 Bread + 1 Milk. */
    public function test_selling_one_bundle_deducts_each_component_by_its_configured_quantity(): void
    {
        $bundleProduct = Product::factory()->create();
        $bundle = ProductBundle::factory()->for($bundleProduct)->create(['bundle_price' => '7500.00']);

        $coffee = Product::factory()->create(['current_stock' => '10.0000']);
        $coffeeUnit = ProductUnit::factory()->for($coffee)->base()->create();

        $bread = Product::factory()->create(['current_stock' => '10.0000']);
        $breadUnit = ProductUnit::factory()->for($bread)->base()->create();

        $milk = Product::factory()->create(['current_stock' => '10.0000']);
        $milkUnit = ProductUnit::factory()->for($milk)->base()->create();

        ProductBundleItem::factory()->for($bundle, 'bundle')->create(['component_product_id' => $coffee->id, 'unit_id' => $coffeeUnit->id, 'quantity' => '1.0000']);
        ProductBundleItem::factory()->for($bundle, 'bundle')->create(['component_product_id' => $bread->id, 'unit_id' => $breadUnit->id, 'quantity' => '1.0000']);
        ProductBundleItem::factory()->for($bundle, 'bundle')->create(['component_product_id' => $milk->id, 'unit_id' => $milkUnit->id, 'quantity' => '1.0000']);

        $this->service->deductComponents($bundle, 1);

        $this->assertSame('9.0000', $coffee->refresh()->current_stock);
        $this->assertSame('9.0000', $bread->refresh()->current_stock);
        $this->assertSame('9.0000', $milk->refresh()->current_stock);
    }

    public function test_selling_multiple_bundles_multiplies_each_component_quantity(): void
    {
        $bundleProduct = Product::factory()->create();
        $bundle = ProductBundle::factory()->for($bundleProduct)->create();

        $coffee = Product::factory()->create(['current_stock' => '50.0000']);
        $coffeeUnit = ProductUnit::factory()->for($coffee)->base()->create();
        ProductBundleItem::factory()->for($bundle, 'bundle')->create(['component_product_id' => $coffee->id, 'unit_id' => $coffeeUnit->id, 'quantity' => '2.0000']);

        $this->service->deductComponents($bundle, 3); // 3 bundles × 2 coffees each = 6

        $this->assertSame('44.0000', $coffee->refresh()->current_stock);
    }
}
