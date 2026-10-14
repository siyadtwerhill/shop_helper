<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_stock_stays_unassigned_when_first_variant_is_created_and_identifiers_are_generated(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = \App\Models\ShopOwner::factory()->create(['user_id' => $user->id]);
        $blueprint = app(\App\Services\BlueprintPresetService::class)->createFashionBlueprint($shop);
        $product = Product::factory()->create([
            'shop_owner_id' => $shop->id,
            'blueprint_id' => $blueprint->id,
            'blueprint_version' => $blueprint->version,
            'stock_mode' => 'own',
        ]);
        $unit = \App\Models\ProductUnit::factory()->forProduct($product)->base()->create();
        app(\App\Services\InventoryMovementService::class)->openingStock($product, $unit, '10', $user->id);

        $this->actingAs($user)->postJson("/api/products/{$product->id}/variants", [
            'attributes' => ['color' => 'Black', 'size' => 'S'],
        ])->assertCreated();

        $product->refresh();
        $variant = $product->variants()->firstOrFail();
        $this->assertSame('10.0000', app(\App\Services\ProductStockService::class)->unassignedFor($product));
        $this->assertSame('10.0000', $product->current_stock);
        $this->assertNotEmpty($variant->sku);
        $this->assertDatabaseHas('product_barcodes', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
        ]);
    }

    public function test_creating_a_variant_with_its_own_sku_and_barcode(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = \App\Models\ShopOwner::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['shop_owner_id' => $shop->id]);
        $blueprint = app(\App\Services\BlueprintPresetService::class)->createFashionBlueprint($shop);
        $product->update(['blueprint_id' => $blueprint->id]);
        \App\Models\ProductUnit::factory()->forProduct($product)->base()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}/variants", [
            'sku' => 'TSHIRT-BLK-S',
            'attributes' => ['color' => 'Black', 'size' => 'S'],
            'selling_price' => 15000,
            'barcode' => '8901111111111',
        ]);

        if ($response->status() !== 201) {
            $response->dump();
        }
        $response->assertCreated();
        $this->assertDatabaseHas('product_variants', ['sku' => 'TSHIRT-BLK-S', 'product_id' => $product->id]);
        $this->assertDatabaseHas('product_barcodes', ['barcode' => '8901111111111', 'product_id' => $product->id]);
    }

    public function test_variant_sku_must_be_globally_unique(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = \App\Models\ShopOwner::factory()->create(['user_id' => $user->id]);
        $productA = Product::factory()->create(['shop_owner_id' => $shop->id]);
        $productB = Product::factory()->create(['shop_owner_id' => $shop->id]);
        $blueprint = app(\App\Services\BlueprintPresetService::class)->createFashionBlueprint($shop);
        $productA->update(['blueprint_id' => $blueprint->id]);
        $productB->update(['blueprint_id' => $blueprint->id]);
        \App\Models\ProductUnit::factory()->forProduct($productA)->base()->create();
        \App\Models\ProductUnit::factory()->forProduct($productB)->base()->create();

        $this->actingAs($user)->postJson("/api/products/{$productA->id}/variants", [
            'sku' => 'DUPLICATE-SKU',
            'attributes' => ['color' => 'Black', 'size' => 'M'],
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/products/{$productB->id}/variants", [
            'sku' => 'DUPLICATE-SKU',
            'attributes' => ['color' => 'White', 'size' => 'M'],
        ])->assertStatus(422);
    }

    public function test_creating_a_variant_logs_a_variant_added_activity_entry(): void
    {
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop = \App\Models\ShopOwner::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create(['shop_owner_id' => $shop->id]);
        $blueprint = app(\App\Services\BlueprintPresetService::class)->createFashionBlueprint($shop);
        $product->update(['blueprint_id' => $blueprint->id]);
        \App\Models\ProductUnit::factory()->forProduct($product)->base()->create();

        $this->actingAs($user)->postJson("/api/products/{$product->id}/variants", [
            'sku' => 'TSHIRT-BLK-M',
            'attributes' => ['color' => 'Black', 'size' => 'M'],
        ]);

        $this->assertDatabaseHas('product_activity_logs', [
            'product_id' => $product->id,
            'action' => 'variants_added',
        ]);
    }
}
