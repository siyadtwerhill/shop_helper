<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_a_unit_selling_price_logs_a_price_changed_entry(): void
    {
        $product = Product::factory()->create();
        $unit = ProductUnit::factory()->for($product)->base()->create(['selling_price' => '60000.00']);

        $unit->update(['selling_price' => '62000.00']);

        $this->assertDatabaseHas('product_activity_logs', [
            'product_id' => $product->id,
            'action' => 'price_changed',
            'old_value' => '60000.00',
            'new_value' => '62000.00',
        ]);
    }

    public function test_changing_a_unit_purchase_price_logs_a_cost_changed_entry(): void
    {
        $product = Product::factory()->create();
        $unit = ProductUnit::factory()->for($product)->base()->create(['purchase_price' => '45000.00']);

        $unit->update(['purchase_price' => '47000.00']);

        $this->assertDatabaseHas('product_activity_logs', [
            'product_id' => $product->id,
            'action' => 'cost_changed',
            'old_value' => '45000.00',
            'new_value' => '47000.00',
        ]);
    }

    public function test_adding_a_barcode_logs_a_barcode_added_entry(): void
    {
        $product = Product::factory()->create();

        ProductBarcode::create([
            'product_id' => $product->id,
            'barcode' => '8909999999999',
            'type' => 'scanned',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('product_activity_logs', [
            'product_id' => $product->id,
            'action' => 'barcode_added',
        ]);
    }
}
