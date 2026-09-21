<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Services\LabelGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    private LabelGeneratorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LabelGeneratorService();
    }

    public function test_builds_a_product_label_with_name_price_and_primary_barcode(): void
    {
        $product = Product::factory()->create(['name' => 'Rice 20kg']);
        $unit = ProductUnit::factory()->for($product)->base()->create(['selling_price' => '90000.00']);
        $product->update(['base_unit_id' => $unit->id]);
        ProductBarcode::create(['product_id' => $product->id, 'barcode' => '8901234567890', 'type' => 'scanned', 'is_primary' => true]);

        $label = $this->service->buildForProduct($product);

        $this->assertSame('Rice 20kg', $label['name']);
        $this->assertSame('8901234567890', $label['barcode']);
        $this->assertSame('90000.00', $label['price']);
    }

    public function test_builds_a_variant_label_using_its_own_price_and_display_name(): void
    {
        $product = Product::factory()->create(['name' => 'T-Shirt']);
        $variant = ProductVariant::factory()->for($product)->create([
            'attributes' => ['color' => 'Black', 'size' => 'M'],
            'selling_price' => '15000.00',
        ]);
        ProductBarcode::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'barcode' => '8909876543210', 'type' => 'scanned', 'is_primary' => true]);

        $label = $this->service->buildForVariant($product, $variant);

        $this->assertSame('T-Shirt (Black / M)', $label['name']);
        $this->assertSame('15000.00', $label['price']);
        $this->assertSame('8909876543210', $label['barcode']);
    }
}
