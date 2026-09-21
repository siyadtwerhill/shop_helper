<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_index_returns_products_for_authenticated_user(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        Product::factory()->for($shop)->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonStructure(['products' => ['data' => [], 'current_page', 'total']]);
    }

    public function test_index_filters_by_search_term(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        Product::factory()->for($shop)->create(['name' => 'Rice']);
        Product::factory()->for($shop)->create(['name' => 'Oil']);

        $response = $this->actingAs($user)->getJson('/api/products?search=Rice');

        $response->assertOk();
        $this->assertCount(1, $response->json('products.data'));
    }

    public function test_index_filters_by_category(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $category = Category::factory()->for($shop)->create();
        Product::factory()->for($shop)->create(['category_id' => $category->id]);
        Product::factory()->for($shop)->create();

        $response = $this->actingAs($user)->getJson("/api/products?category_id={$category->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('products.data'));
    }

    public function test_show_returns_product_details(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('product.id', $product->id);
    }

    public function test_update_modifies_product(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New Name']);
    }



    public function test_destroy_archives_product(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create(['status' => 'active']);

        $response = $this->actingAs($user)->deleteJson("/api/products/{$product->id}");

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'archived',
        ]);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_lookup_by_barcode_returns_product_when_found(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        ProductBarcode::factory()->for($product)->create(['barcode' => '8901234567890']);

        $response = $this->actingAs($user)->postJson('/api/products/lookup-barcode', [
            'barcode' => '8901234567890',
        ]);

        $response->assertOk();
        $response->assertJsonPath('found', true);
        $response->assertJsonPath('product.id', $product->id);
    }

    public function test_lookup_by_barcode_returns_not_found_when_unknown(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $response = $this->actingAs($user)->postJson('/api/products/lookup-barcode', [
            'barcode' => '0000000000000',
        ]);

        $response->assertOk();
        $response->assertJsonPath('found', false);
        $response->assertJsonPath('barcode', '0000000000000');
    }
}
