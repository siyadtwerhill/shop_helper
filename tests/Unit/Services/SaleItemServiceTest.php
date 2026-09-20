<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\ShopOwner;
use App\Services\InventoryMovementService;
use App\Services\SaleItemService;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SaleItemServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaleItemService $service;
    private int $saleId;

    protected function setUp(): void
    {
        parent::setUp();
        $conversion = new UnitConversionService();
        $this->service = new SaleItemService(new InventoryMovementService($conversion), $conversion);
        ShopOwner::factory()->create();
        $this->saleId = Sale::factory()->create()->id;
    }

    public function test_selling_in_a_non_base_unit_records_correct_base_quantity_and_deducts_stock(): void
    {
        $product = Product::factory()->create(['current_stock' => '100.0000']);
        ProductUnit::factory()->for($product)->base()->create(); // KG
        $bag = ProductUnit::factory()->for($product)->create(['conversion_factor' => '20.0000']);

        $saleItem = $this->service->sell($product, $bag, 2, '90000.00', saleId: $this->saleId);

        $this->assertSame('2.0000', $saleItem->quantity);
        $this->assertSame('40.0000', $saleItem->base_quantity);
        $this->assertSame('60.0000', $product->refresh()->current_stock);
    }

    public function test_cannot_sell_a_unit_that_is_not_marked_sellable(): void
    {
        $product = Product::factory()->create(['current_stock' => '10.0000']);
        $unit = ProductUnit::factory()->for($product)->base()->create(['is_sellable' => false]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->sell($product, $unit, 1, '1000.00', saleId: $this->saleId);
    }

    /** Spec §5: a negotiated price must never overwrite the list price — they're stored separately. */
    public function test_original_unit_price_defaults_to_the_unit_list_price_when_not_given(): void
    {
        $product = Product::factory()->create(['current_stock' => '10.0000']);
        $unit = ProductUnit::factory()->for($product)->base()->create(['selling_price' => '4500.00']);

        $saleItem = $this->service->sell($product, $unit, 1, '4000.00', saleId: $this->saleId);

        $this->assertSame('4000.00', $saleItem->unit_price);
        $this->assertSame('4500.00', $saleItem->original_unit_price);
    }
}