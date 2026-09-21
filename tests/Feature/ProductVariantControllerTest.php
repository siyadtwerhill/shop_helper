<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_variant_with_its_own_sku_and_barcode(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/variants", [
            'sku' => 'TSHIRT-BLK-S',
            'attributes' => ['color' => 'Black', 'size' => 'S'],
            'selling_price' => 15000,
            'barcode' => '8901111111111',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('product_variants', ['sku' => 'TSHIRT-BLK-S', 'product_id' => $product->id]);
        $this->assertDatabaseHas('product_barcodes', ['barcode' => '8901111111111', 'product_id' => $product->id]);
    }

    public function test_variant_sku_must_be_globally_unique(): void
    {
        $user = User::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $this->actingAs($user)->postJson("/api/products/{$productA->id}/variants", [
            'sku' => 'DUPLICATE-SKU',
            'attributes' => ['color' => 'Black'],
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/products/{$productB->id}/variants", [
            'sku' => 'DUPLICATE-SKU',
            'attributes' => ['color' => 'White'],
        ])->assertStatus(422);
    }

    public function test_creating_a_variant_logs_a_variant_added_activity_entry(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->postJson("/api/products/{$product->id}/variants", [
            'sku' => 'TSHIRT-BLK-M',
            'attributes' => ['color' => 'Black', 'size' => 'M'],
        ]);

        $this->assertDatabaseHas('product_activity_logs', [
            'product_id' => $product->id,
            'action' => 'variant_added',
        ]);
    }
}
