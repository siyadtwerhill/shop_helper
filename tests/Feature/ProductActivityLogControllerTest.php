<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductActivityLog;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductActivityLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_index_returns_activity_logs_for_product(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        ProductActivityLog::factory()->for($product)->count(5)->create();

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/activity");

        $response->assertOk();
        $response->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertCount(5, $response->json('data'));
    }

    public function test_index_includes_user_relationship(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        ProductActivityLog::factory()->for($product)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/activity");

        $response->assertOk();
        $this->assertArrayHasKey('user', $response->json('data.0'));
    }

    public function test_index_returns_empty_when_no_logs(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/activity");

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_paginates_results(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $product = Product::factory()->for($shop)->create();
        ProductActivityLog::factory()->for($product)->count(30)->create();

        $response = $this->actingAs($user)->getJson("/api/products/{$product->id}/activity");

        $response->assertOk();
        $this->assertLessThanOrEqual(25, count($response->json('data')));
        $this->assertGreaterThan(0, $response->json('total'));
    }
}
