<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_index_returns_brands_for_authenticated_user(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        Brand::factory()->for($shop)->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/brands');

        $response->assertOk();
        $response->assertJsonStructure(['brands']);
        $this->assertCount(3, $response->json('brands'));
    }

    public function test_store_creates_brand(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $response = $this->actingAs($user)->postJson('/api/brands', [
            'name' => 'Coca Cola',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('brands', [
            'name' => 'Coca Cola',
            'shop_owner_id' => $shop->id,
            'slug' => 'coca-cola',
        ]);
    }

    public function test_show_returns_brand(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $brand = Brand::factory()->for($shop)->create();

        $response = $this->actingAs($user)->getJson("/api/brands/{$brand->id}");

        $response->assertOk();
        $response->assertJsonPath('brand.id', $brand->id);
    }

    public function test_update_modifies_brand(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $brand = Brand::factory()->for($shop)->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->putJson("/api/brands/{$brand->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'New Name',
            'slug' => 'new-name',
        ]);
    }

    public function test_delete_deletes_brand(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $brand = Brand::factory()->for($shop)->create();

        $response = $this->actingAs($user)->deleteJson("/api/brands/{$brand->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }
}
