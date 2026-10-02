<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\Product;
use App\Models\ShopOwner;
use App\Services\BlueprintPresetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBlueprintIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private ShopOwner $shop;
    private BlueprintPresetService $presetService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = ShopOwner::factory()->create();
        $this->presetService = new BlueprintPresetService();
    }

    public function test_product_can_have_blueprint()
    {
        $blueprint = $this->presetService->createGenericBlueprint($this->shop);

        $product = Product::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'blueprint_id' => $blueprint->id,
            'blueprint_version' => 1,
            'stock_mode' => 'own',
        ]);

        $this->assertEquals($blueprint->id, $product->blueprint_id);
        $this->assertEquals(1, $product->blueprint_version);
        $this->assertEquals('own', $product->stock_mode);
    }

    public function test_product_belongs_to_blueprint()
    {
        $blueprint = $this->presetService->createGenericBlueprint($this->shop);

        $product = Product::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'blueprint_id' => $blueprint->id,
        ]);

        $this->assertInstanceOf(Blueprint::class, $product->blueprint);
        $this->assertEquals($blueprint->id, $product->blueprint->id);
    }

    public function test_blueprint_has_many_products()
    {
        $blueprint = $this->presetService->createGenericBlueprint($this->shop);

        $products = Product::factory()->count(3)->create([
            'shop_owner_id' => $this->shop->id,
            'blueprint_id' => $blueprint->id,
        ]);

        $this->assertCount(3, $blueprint->products);
        foreach ($products as $product) {
            $this->assertTrue($blueprint->products->contains($product));
        }
    }

    public function test_product_attributes_is_json()
    {
        $blueprint = $this->presetService->createGenericBlueprint($this->shop);

        $attributes = [
            'material' => 'Cotton',
            'season' => 'Summer',
        ];

        $product = Product::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'blueprint_id' => $blueprint->id,
            'attributes' => $attributes,
        ]);

        $this->assertIsArray($product->attributes);
        $this->assertEquals($attributes, $product->attributes);
    }

    public function test_product_stock_mode_enum_values()
    {
        $blueprint = $this->presetService->createGenericBlueprint($this->shop);

        $product = Product::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'blueprint_id' => $blueprint->id,
            'stock_mode' => 'own',
        ]);

        $this->assertEquals('own', $product->stock_mode);

        $product->update(['stock_mode' => 'from_variants']);
        $this->assertEquals('from_variants', $product->stock_mode);

        $product->update(['stock_mode' => 'from_components']);
        $this->assertEquals('from_components', $product->stock_mode);
    }

    public function test_generic_blueprint_creates_without_fields()
    {
        $blueprint = $this->presetService->createGenericBlueprint($this->shop);

        $this->assertCount(0, $blueprint->fields);
    }

    public function test_fashion_blueprint_creates_with_fields()
    {
        $blueprint = $this->presetService->createFashionBlueprint($this->shop);

        $this->assertCount(4, $blueprint->fields);
    }

    public function test_product_with_fashion_blueprint()
    {
        $blueprint = $this->presetService->createFashionBlueprint($this->shop);

        $product = Product::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'blueprint_id' => $blueprint->id,
            'stock_mode' => 'from_variants',
            'attributes' => [
                'material' => 'Cotton',
                'season' => 'Summer',
            ],
        ]);

        $this->assertEquals($blueprint->id, $product->blueprint_id);
        $this->assertEquals('from_variants', $product->stock_mode);
        $this->assertEquals('Cotton', $product->attributes['material']);
        $this->assertEquals('Summer', $product->attributes['season']);
    }

    public function test_blueprint_versioning()
    {
        $blueprint = $this->presetService->createGenericBlueprint($this->shop);

        $product1 = Product::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'blueprint_id' => $blueprint->id,
            'blueprint_version' => 1,
        ]);

        $this->assertEquals(1, $product1->blueprint_version);

        // Update blueprint version
        $blueprint->update(['version' => 2]);

        $product2 = Product::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'blueprint_id' => $blueprint->id,
            'blueprint_version' => 2,
        ]);

        $this->assertEquals(2, $product2->blueprint_version);
    }

    public function test_shop_owner_has_blueprints()
    {
        $blueprint1 = $this->presetService->createGenericBlueprint($this->shop);
        $blueprint2 = $this->presetService->createFashionBlueprint($this->shop);

        $this->assertCount(2, $this->shop->blueprints);
        $this->assertTrue($this->shop->blueprints->contains($blueprint1));
        $this->assertTrue($this->shop->blueprints->contains($blueprint2));
    }

    public function test_product_stock_mode_follows_blueprint_variant_capability()
    {
        $genericBlueprint = $this->presetService->createGenericBlueprint($this->shop);
        $fashionBlueprint = $this->presetService->createFashionBlueprint($this->shop);

        $this->assertFalse($genericBlueprint->capabilities['variants']);
        $this->assertTrue($fashionBlueprint->capabilities['variants']);

        $genericProduct = Product::factory()->withBlueprint($genericBlueprint)->create([
            'shop_owner_id' => $this->shop->id,
        ]);
        $fashionProduct = Product::factory()->withBlueprint($fashionBlueprint)->create([
            'shop_owner_id' => $this->shop->id,
        ]);

        $this->assertEquals('own', $genericProduct->stock_mode);
        $this->assertEquals('from_variants', $fashionProduct->stock_mode);
    }
}
