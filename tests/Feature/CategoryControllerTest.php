<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_index_returns_categories_for_authenticated_user(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        Category::factory()->for($shop)->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/categories');

        $response->assertOk();
        $response->assertJsonStructure(['categories']);
        $this->assertCount(3, $response->json('categories'));
    }

    public function test_store_creates_category(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        $response = $this->actingAs($user)->postJson('/api/categories', [
            'name' => 'Food',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('categories', [
            'name' => 'Food',
            'shop_owner_id' => $shop->id,
            'slug' => 'food',
        ]);
    }

    public function test_store_creates_category_with_parent(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $parent = Category::factory()->for($shop)->create();

        $response = $this->actingAs($user)->postJson('/api/categories', [
            'name' => 'Rice',
            'parent_id' => $parent->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('categories', [
            'name' => 'Rice',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_show_returns_category_with_relationships(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $category = Category::factory()->for($shop)->create();

        $response = $this->actingAs($user)->getJson("/api/categories/{$category->id}");

        $response->assertOk();
        $response->assertJsonPath('category.id', $category->id);
    }

    public function test_update_modifies_category(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $category = Category::factory()->for($shop)->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->putJson("/api/categories/{$category->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
            'slug' => 'new-name',
        ]);
    }

    public function test_update_changes_parent(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $category = Category::factory()->for($shop)->create();
        $newParent = Category::factory()->for($shop)->create();

        $response = $this->actingAs($user)->putJson("/api/categories/{$category->id}", [
            'parent_id' => $newParent->id,
        ]);

        $response->assertOk();
        $this->assertEquals($newParent->id, $category->refresh()->parent_id);
    }

    public function test_deletes_category(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();
        $category = Category::factory()->for($shop)->create();

        $response = $this->actingAs($user)->deleteJson("/api/categories/{$category->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
