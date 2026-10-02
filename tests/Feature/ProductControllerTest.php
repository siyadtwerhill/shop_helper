<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ShopOwner;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Models\Branch;
use Spatie\Permission\PermissionRegistrar;

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

    public function test_update_changes_base_unit_selling_price(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        $unit = \App\Models\ProductUnit::factory()->for($product)->base()->create([
            'selling_price' => '100.00',
        ]);

        $response = $this->actingAs($user)->putJson("/api/products/{$product->id}", [
            'selling_price' => '250.50',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_units', [
            'id' => $unit->id,
            'selling_price' => '250.50',
        ]);
    }

    public function test_update_via_post_saves_price_unit_conversion_and_stock(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create(['current_stock' => '5.0000']);
        $productUnit = \App\Models\ProductUnit::factory()->for($product)->base()->create([
            'selling_price' => '100.00',
            'conversion_factor' => '1.0000',
        ]);
        $newCatalogUnit = \App\Models\Unit::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/products/{$product->id}", [
            'selling_price' => '250.50',
            'unit_id' => $newCatalogUnit->id,
            'conversion_factor' => '2.5000',
            'current_stock' => '12',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_units', [
            'id' => $productUnit->id,
            'selling_price' => '250.50',
            'unit_id' => $newCatalogUnit->id,
            'conversion_factor' => '2.5000',
        ]);
        $this->assertEquals(0, bccomp((string) $product->fresh()->current_stock, '12', 4));
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

    public function test_branch_head_only_sees_their_branch_products(): void
    {
        $shop = ShopOwner::factory()->create();
        $branch1 = Branch::factory()->create(['shop_owner_id' => $shop->id]);
        $branch2 = Branch::factory()->create(['shop_owner_id' => $shop->id]);

        // Create branch head user manually
        $branchHeadUser = User::create([
            'name' => 'Branch Head',
            'username' => 'branch_head',
            'email' => 'head@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);

        $branchHead = Staff::create([
            'user_id' => $branchHeadUser->id,
            'shop_owner_id' => $shop->id,
            'branch_id' => $branch1->id,
            'status' => 'active',
        ]);

        // Set team context for permissions
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($shop->id);

        // Assign branch_head role
        $branchHeadRole = \Spatie\Permission\Models\Role::create([
            'name' => 'branch_head',
            'guard_name' => 'web',
            'shop_owner_id' => $shop->id,
        ]);
        $branchHeadUser->assignRole($branchHeadRole);

        // Create products in both branches
        Product::factory()->for($shop)->forBranch($branch1->id)->create(['name' => 'Branch 1 Product']);
        Product::factory()->for($shop)->forBranch($branch2->id)->create(['name' => 'Branch 2 Product']);

        $response = $this->actingAs($branchHeadUser)->getJson('/api/products');

        $response->assertOk();
        $products = $response->json('products.data');
        $this->assertCount(1, $products);
        $this->assertEquals('Branch 1 Product', $products[0]['name']);
    }
}
