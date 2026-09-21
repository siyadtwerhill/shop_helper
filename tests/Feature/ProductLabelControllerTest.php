<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductLabelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_show_returns_product_label_data(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create(['name' => 'Test Product', 'sku' => 'TEST-001']);
        $unit = ProductUnit::factory()->for($product)->base()->create(['selling_price' => '100.00']);
        ProductBarcode::factory()->for($product)->create(['barcode' => '8901234567890', 'is_primary' => true]);

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/label");

        $response->assertOk();
        $response->assertJsonPath('product_id', $product->id);
        $response->assertJsonPath('name', 'Test Product');
        $response->assertJsonPath('sku', 'TEST-001');
        $response->assertJsonPath('price', '100.00');
        $response->assertJsonPath('barcode', '8901234567890');
        $response->assertJsonPath('variant_id', null);
    }

    public function test_show_returns_null_price_when_no_base_unit(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/label");

        $response->assertOk();
        $response->assertJsonPath('price', null);
    }

    public function test_show_returns_null_barcode_when_no_primary_barcode(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/label");

        $response->assertOk();
        $response->assertJsonPath('barcode', null);
    }

    public function test_show_returns_qr_url_when_present(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create(['qr_path' => 'qrs/test.png']);

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/label");

        $response->assertOk();
        $response->assertJsonPath('qr_code_url', 'http://localhost/storage/qrs/test.png');
    }

    public function test_variant_returns_variant_label_data(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create(['name' => 'T-Shirt']);
        $variant = ProductVariant::factory()->for($product)->create([
            'sku' => 'TSHIRT-BLK-M',
            'attributes' => ['color' => 'Black', 'size' => 'M'],
            'selling_price' => '150.00',
        ]);
        ProductBarcode::factory()->for($product)->create([
            'product_variant_id' => $variant->id,
            'barcode' => '8909876543210',
            'is_primary' => true,
        ]);

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/variants/{$variant->id}/label");

        $response->assertOk();
        $response->assertJsonPath('product_id', $product->id);
        $response->assertJsonPath('variant_id', $variant->id);
        $response->assertJsonPath('name', 'T-Shirt (Black / M)');
        $response->assertJsonPath('sku', 'TSHIRT-BLK-M');
        $response->assertJsonPath('price', '150.00');
        $response->assertJsonPath('barcode', '8909876543210');
    }

    public function test_variant_falls_back_to_product_barcode(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $variant = ProductVariant::factory()->for($product)->create();
        ProductBarcode::factory()->for($product)->create(['barcode' => '8901234567890', 'is_primary' => true]);

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/variants/{$variant->id}/label");

        $response->assertOk();
        $response->assertJsonPath('barcode', '8901234567890');
    }

    public function test_variant_uses_base_unit_price_when_variant_price_null(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $unit = ProductUnit::factory()->for($product)->base()->create(['selling_price' => '100.00']);
        $variant = ProductVariant::factory()->for($product)->create(['selling_price' => null]);

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/variants/{$variant->id}/label");

        $response->assertOk();
        $response->assertJsonPath('price', '100.00');
    }

    public function test_variant_fails_for_wrong_product(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $productA = Product::factory()->for($shop)->create();
        $productB = Product::factory()->for($shop)->create();
        $variant = ProductVariant::factory()->for($productA)->create();

        $response = $this->actingAs($user)->getJson("/api/products/{$productB->id}/variants/{$variant->id}/label");

        $response->assertNotFound();
    }
}
