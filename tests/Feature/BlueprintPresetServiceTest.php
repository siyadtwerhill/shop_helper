<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\FieldDefinition;
use App\Models\BlueprintField;
use App\Models\ShopOwner;
use App\Services\BlueprintPresetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlueprintPresetServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlueprintPresetService $service;
    private ShopOwner $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BlueprintPresetService();
        $this->shop = ShopOwner::factory()->create();
    }

    public function test_create_generic_blueprint()
    {
        $blueprint = $this->service->createGenericBlueprint($this->shop);

        $this->assertInstanceOf(Blueprint::class, $blueprint);
        $this->assertEquals('Generic', $blueprint->name);
        $this->assertEquals('generic', $blueprint->preset_key);
        $this->assertTrue($blueprint->is_default);
        $this->assertEquals('active', $blueprint->status);
        $this->assertEquals($this->shop->id, $blueprint->shop_owner_id);

        $this->assertEquals([
            'variants' => false,
            'bundles' => false,
            'batch_expiry' => false,
            'serial_numbers' => false,
            'decimal_quantities' => false,
            'multiple_units' => true,
        ], $blueprint->capabilities);

        $this->assertEquals([
            'allowed_modes' => ['fixed', 'negotiable', 'wholesale'],
            'default_mode' => 'fixed',
            'min_margin_percent' => 15,
            'cost_required' => true,
            'block_below_margin' => false,
            'price_per_variant' => false,
        ], $blueprint->pricing_policy);

        $this->assertEquals([
            'default_base_unit' => 'piece',
            'allowed_units' => ['piece', 'box', 'dozen'],
            'price_per_unit' => true,
            'barcode_per_unit' => false,
            'buy_sell_different_units' => true,
        ], $blueprint->unit_policy);
    }

    public function test_create_fashion_blueprint()
    {
        $blueprint = $this->service->createFashionBlueprint($this->shop);

        $this->assertInstanceOf(Blueprint::class, $blueprint);
        $this->assertEquals('Fashion Retail', $blueprint->name);
        $this->assertEquals('fashion', $blueprint->preset_key);
        $this->assertFalse($blueprint->is_default);

        $this->assertEquals([
            'variants' => true,
            'bundles' => true,
            'batch_expiry' => false,
            'serial_numbers' => false,
            'decimal_quantities' => false,
            'multiple_units' => true,
        ], $blueprint->capabilities);

        $this->assertEquals([
            'allowed_modes' => ['fixed', 'negotiable', 'wholesale'],
            'default_mode' => 'fixed',
            'min_margin_percent' => 15,
            'cost_required' => true,
            'block_below_margin' => false,
            'price_per_variant' => true,
        ], $blueprint->pricing_policy);

        // Check that fields were created
        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'material',
        ]);

        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'season',
        ]);

        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'color',
        ]);

        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'size',
        ]);

        // Check that blueprint fields were created
        $this->assertDatabaseHas('blueprint_fields', [
            'blueprint_id' => $blueprint->id,
            'section' => 'details',
        ]);

        $this->assertDatabaseHas('blueprint_fields', [
            'blueprint_id' => $blueprint->id,
            'section' => 'variant_axes',
            'is_variant_axis' => true,
        ]);
    }

    public function test_fashion_blueprint_has_correct_field_settings()
    {
        $blueprint = $this->service->createFashionBlueprint($this->shop);

        $colorField = FieldDefinition::where('key', 'color')->first();
        $colorBlueprintField = BlueprintField::where('field_definition_id', $colorField->id)->first();

        $this->assertEquals('Color', $colorField->label);
        $this->assertEquals('select', $colorField->type);
        $this->assertNotNull($colorField->options);
        $this->assertIsArray($colorField->options);
        $this->assertContains('Black', $colorField->options);

        $this->assertEquals('variant_axes', $colorBlueprintField->section);
        $this->assertTrue($colorBlueprintField->required);
        $this->assertTrue($colorBlueprintField->is_variant_axis);
        $this->assertTrue($colorBlueprintField->show_in_list);
        $this->assertTrue($colorBlueprintField->show_in_pos);
        $this->assertTrue($colorBlueprintField->show_on_label);
        $this->assertTrue($colorBlueprintField->is_filterable);
        $this->assertTrue($colorBlueprintField->is_searchable);
    }

    public function test_get_available_presets()
    {
        $presets = $this->service->getAvailablePresets();

        $this->assertIsArray($presets);
        $this->assertCount(6, $presets);

        $presetKeys = array_column($presets, 'key');
        $this->assertContains('generic', $presetKeys);
        $this->assertContains('fashion', $presetKeys);
        $this->assertContains('grocery', $presetKeys);
        $this->assertContains('pharmacy', $presetKeys);
        $this->assertContains('electronics', $presetKeys);
        $this->assertContains('cafe', $presetKeys);

        foreach ($presets as $preset) {
            $this->assertArrayHasKey('key', $preset);
            $this->assertArrayHasKey('name', $preset);
            $this->assertArrayHasKey('description', $preset);
            $this->assertArrayHasKey('icon', $preset);
        }
    }

    public function test_generic_blueprint_has_no_fields()
    {
        $blueprint = $this->service->createGenericBlueprint($this->shop);

        $this->assertCount(0, $blueprint->fields);
    }

    public function test_fashion_blueprint_has_variant_axes_in_layout()
    {
        $blueprint = $this->service->createFashionBlueprint($this->shop);

        $layout = $blueprint->layout;

        $this->assertArrayHasKey('sections', $layout);
        $this->assertArrayHasKey('variant_axes', $layout['sections']);
        $this->assertEquals('Variant Axes', $layout['sections']['variant_axes']['label']);
        $this->assertEquals(2, $layout['sections']['variant_axes']['sort_order']);
    }

    public function test_generic_blueprint_has_basic_layout()
    {
        $blueprint = $this->service->createGenericBlueprint($this->shop);

        $layout = $blueprint->layout;

        $this->assertArrayHasKey('sections', $layout);
        $this->assertCount(3, $layout['sections']);
        $this->assertArrayHasKey('details', $layout['sections']);
        $this->assertArrayHasKey('pricing', $layout['sections']);
        $this->assertArrayHasKey('inventory', $layout['sections']);
    }
}
