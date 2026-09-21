<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ProductUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleSaleItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_selling_a_bundle_creates_a_sale_item_and_deducts_component_stock(): void
    {
        $user = User::factory()->create();

        $bundleProduct = Product::factory()->create();
        $bundleUnit = ProductUnit::factory()->for($bundleProduct)->base()->create();
        $bundle = ProductBundle::factory()->for($bundleProduct)->create(['bundle_price' => '7500.00']);

        $coffee = Product::factory()->create(['current_stock' => '10.0000']);
        $coffeeUnit = ProductUnit::factory()->for($coffee)->base()->create();
        ProductBundleItem::factory()->for($bundle, 'bundle')->create([
            'component_product_id' => $coffee->id,
            'unit_id' => $coffeeUnit->id,
            'quantity' => '1.0000',
        ]);

        $response = $this->actingAs($user)->postJson('/api/pos/bundle-sale-items', [
            'sale_id' => 1,
            'product_id' => $bundleProduct->id,
            'unit_id' => $bundleUnit->id,
            'quantity' => 1,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('sale_items', ['product_id' => $bundleProduct->id, 'unit_price' => '7500.00']);
        $this->assertSame('9.0000', $coffee->refresh()->current_stock);
        $this->assertSame('0.0000', $bundleProduct->refresh()->current_stock); // bundle itself is never inventory-tracked
    }
}
