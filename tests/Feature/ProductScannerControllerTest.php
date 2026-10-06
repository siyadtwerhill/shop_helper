<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductScannerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ShopOwner::factory()->create();
    }

    public function test_scanning_a_known_barcode_returns_the_product(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $product = Product::factory()->for($shop)->create();
        ProductBarcode::factory()->create([
            'product_id' => $product->id,
            'shop_owner_id' => $shop->id,
            'barcode' => '8901234567890',
        ]);

        $response = $this->actingAs($user)->postJson('/api/scanner/lookup', ['code' => '8901234567890']);

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $response->assertJsonPath('product.id', $product->id);
    }

    public function test_scanning_an_unknown_barcode_returns_404_with_the_code_for_prefill(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $response = $this->actingAs($user)->postJson('/api/scanner/lookup', ['code' => '0000000000000']);

        $response->assertStatus(404);
        $response->assertJsonPath('found', false);
        $response->assertJsonPath('scanned_code', '0000000000000');
    }
}