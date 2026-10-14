<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\BlueprintField;
use App\Models\FieldDefinition;
use App\Models\Product;
use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use App\Services\BlueprintValidationBuilder;
use App\Services\BlueprintPresetService;
use App\Services\ProductSystemFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProductSystemFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $shopOwner;
    private ShopOwner $shop;
    private BlueprintPresetService $presetService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shopOwner = User::factory()->create(['role' => 'shop_owner']);
        $this->shop = ShopOwner::factory()->create();
        $this->shop->user()->associate($this->shopOwner);
        $this->shop->save();
        $this->presetService = new BlueprintPresetService();
        Storage::fake('public');
    }

    private function png(string $name = 'p.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
        );
    }

    public function test_hidden_image_is_not_stored_on_create()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $bp->update(['system_fields' => ['image' => ['hidden' => true]]]);
        $unit = \App\Models\Unit::factory()->create();

        $response = $this->actingAs($this->shopOwner)->post('/api/products', [
            'name' => 'P',
            'selling_price' => '10',
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
            'image' => $this->png(),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertNull(Product::latest('id')->first()->image_path);
        $this->assertEmpty(Storage::disk('public')->allFiles('products'));
    }

    public function test_hidden_image_is_not_replaced_on_update()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $bp->update(['system_fields' => ['image' => ['hidden' => true]]]);
        Storage::disk('public')->put('products/old.jpg', 'x');
        $product = Product::factory()->for($this->shop)->create([
            'blueprint_id' => $bp->id,
            'image_path' => 'products/old.jpg',
        ]);

        $this->actingAs($this->shopOwner)->post("/api/products/{$product->id}", [
            'name' => 'New',
            'image' => $this->png(),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('products/old.jpg', $product->fresh()->image_path);
        Storage::disk('public')->assertExists('products/old.jpg');
        $this->assertCount(1, Storage::disk('public')->allFiles('products'));
    }

    public function test_hidden_image_is_not_removed_on_update()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $bp->update(['system_fields' => ['image' => ['hidden' => true]]]);

        Storage::disk('public')->put('products/test.jpg', 'x');
        $product = Product::factory()->for($this->shop)->create([
            'blueprint_id' => $bp->id,
            'image_path' => 'products/test.jpg',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->postJson("/api/products/{$product->id}", [
                'name' => 'Updated Name',
                'remove_image' => '1',
            ]);

        $response->assertStatus(200);
        $product->refresh();
        $this->assertEquals('products/test.jpg', $product->image_path);
        Storage::disk('public')->assertExists('products/test.jpg');
    }

    public function test_required_image_cannot_be_removed_on_update()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $bp->update(['system_fields' => ['image' => ['required' => true]]]);

        $product = Product::factory()->for($this->shop)->create([
            'blueprint_id' => $bp->id,
            'image_path' => 'products/test.jpg',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->postJson("/api/products/{$product->id}", [
                'name' => 'Updated Name',
                'remove_image' => '1',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'An image is required for this product type.');
    }

    public function test_resolve_returns_defaults_for_null_system_fields()
    {
        $bp = Blueprint::factory()->for($this->shop)->create(['system_fields' => null]);

        $resolved = ProductSystemFields::resolve($bp);

        $this->assertArrayHasKey('name', $resolved);
        $this->assertFalse($resolved['name']['hidden']);
        // ProductController's base rules require name; the system-field registry cannot toggle it.
        $this->assertFalse($resolved['name']['required']);

        $this->assertArrayHasKey('description', $resolved);
        $this->assertFalse($resolved['description']['hidden']);
        $this->assertFalse($resolved['description']['required']);
    }

    public function test_sanitize_drops_unknown_keys()
    {
        $input = [
            'description' => ['hidden' => true],
            'unknown_field' => ['hidden' => true],
        ];

        $sanitized = ProductSystemFields::sanitize($input);

        $this->assertArrayHasKey('description', $sanitized);
        $this->assertArrayNotHasKey('unknown_field', $sanitized);
    }

    public function test_sanitize_ignores_hidden_on_cannot_hide_fields()
    {
        $input = [
            'name' => ['hidden' => true], // can_hide = false
            'min_price' => ['hidden' => true], // can_require = false, but can_hide = true
        ];

        $sanitized = ProductSystemFields::sanitize($input);

        $this->assertArrayNotHasKey('name', $sanitized);
        $this->assertArrayHasKey('min_price', $sanitized);
    }

    public function test_sanitize_ignores_required_on_cannot_require_fields()
    {
        $input = [
            'name' => ['required' => true], // can_require = false
            'min_price' => ['required' => true], // can_require = false
        ];

        $sanitized = ProductSystemFields::sanitize($input);

        $this->assertArrayNotHasKey('name', $sanitized);
        $this->assertArrayNotHasKey('min_price', $sanitized);
    }

    public function test_hidden_field_cannot_also_be_required()
    {
        $bp = Blueprint::factory()->for($this->shop)->create([
            'system_fields' => ['description' => ['hidden' => true, 'required' => true]],
        ]);

        $resolved = ProductSystemFields::resolve($bp);

        $this->assertTrue($resolved['description']['hidden']);
        $this->assertFalse($resolved['description']['required']);
    }

    public function test_cost_price_locked_by_pricing_policy()
    {
        $bp = Blueprint::factory()->for($this->shop)->create([
            'pricing_policy' => ['cost_required' => true],
            'system_fields' => ['cost_price' => ['hidden' => true, 'required' => false]],
        ]);

        $resolved = ProductSystemFields::resolve($bp);

        $this->assertFalse($resolved['cost_price']['hidden']);
        $this->assertTrue($resolved['cost_price']['required']);
    }

    public function test_required_brand_id_blocks_create()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $bp->update(['system_fields' => ['brand_id' => ['required' => true]]]);

        $unit = \App\Models\Unit::factory()->create();

        $response = $this->actingAs($this->shopOwner)
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'selling_price' => '100.00',
                'unit_id' => $unit->id,
                'blueprint_id' => $bp->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('brand_id');
    }

    public function test_fractional_min_stock_is_rejected_when_decimal_quantities_are_disabled(): void
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $bp->update(['capabilities' => ['decimal_quantities' => false]]);
        $unit = \App\Models\Unit::factory()->create();

        $this->actingAs($this->shopOwner)->postJson('/api/products', [
            'name' => 'Whole quantity product',
            'selling_price' => 10,
            'cost_price' => 5,
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
            'min_stock' => '1.5',
        ])->assertStatus(422);
    }

    public function test_required_brand_id_passes_when_sent()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $bp->update(['system_fields' => ['brand_id' => ['required' => true]]]);

        $brand = \App\Models\Brand::factory()->for($this->shop)->create();
        $unit = \App\Models\Unit::factory()->create();

        $response = $this->actingAs($this->shopOwner)
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'selling_price' => '100.00',
                'unit_id' => $unit->id,
                'blueprint_id' => $bp->id,
                'brand_id' => $brand->id,
            ]);

        $response->assertStatus(201);
    }

    public function test_hidden_field_not_saved_on_create()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $bp->update(['system_fields' => ['description' => ['hidden' => true]]]);

        $unit = \App\Models\Unit::factory()->create();

        $response = $this->actingAs($this->shopOwner)
            ->postJson('/api/products', [
                'name' => 'Test Product',
                'selling_price' => '100.00',
                'unit_id' => $unit->id,
                'blueprint_id' => $bp->id,
                'description' => 'This should be ignored',
            ]);

        $response->assertStatus(201);
        $this->assertNull(Product::latest('id')->first()->description);
    }

    public function test_hidden_field_keeps_stored_value_on_update()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $bp->update(['system_fields' => ['description' => ['hidden' => true]]]);

        $unit = \App\Models\Unit::factory()->create();
        $product = Product::factory()->for($this->shop)->create([
            'blueprint_id' => $bp->id,
            'description' => 'Original description',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->postJson("/api/products/{$product->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);
        $product->refresh();
        $this->assertEquals('Original description', $product->description);
    }

    public function test_apply_system_fields_respects_skip_required()
    {
        $bp = Blueprint::factory()->for($this->shop)->create([
            'system_fields' => [
                'cost_price' => ['required' => true],
                'min_stock' => ['required' => true],
            ],
        ]);
        $base = ['cost_price' => 'nullable|numeric', 'min_stock' => 'nullable|numeric'];
        $builder = app(BlueprintValidationBuilder::class);

        $rules = $builder->applySystemFields($bp, $base, skipRequired: ['cost_price', 'min_stock']);
        $this->assertSame($base, $rules);

        $rules = $builder->applySystemFields($bp, $base);
        $this->assertContains('required', $rules['cost_price']);
        $this->assertNotContains('nullable', $rules['min_stock']);
    }

    public function test_apply_system_fields_hidden_removes_rule()
    {
        $bp = Blueprint::factory()->for($this->shop)->create([
            'system_fields' => ['description' => ['hidden' => true]],
        ]);

        $rules = app(BlueprintValidationBuilder::class)->applySystemFields($bp, [
            'name' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayNotHasKey('description', $rules);
    }

    public function test_apply_system_fields_partial_required_uses_sometimes_required()
    {
        $bp = Blueprint::factory()->for($this->shop)->create([
            'system_fields' => ['brand_id' => ['required' => true]],
        ]);

        $rules = app(BlueprintValidationBuilder::class)->applySystemFields($bp, [
            'brand_id' => 'nullable|integer',
        ], partial: true);

        $this->assertSame(['sometimes', 'required', 'integer'], $rules['brand_id']);
    }

    public function test_apply_system_fields_keeps_rule_objects()
    {
        $bp = Blueprint::factory()->for($this->shop)->create([
            'system_fields' => ['brand_id' => ['required' => true]],
        ]);
        $exists = Rule::exists('brands', 'id')->where('shop_owner_id', $this->shop->id);

        $rules = app(BlueprintValidationBuilder::class)->applySystemFields($bp, [
            'brand_id' => ['nullable', $exists],
        ]);

        $this->assertSame($exists, $rules['brand_id'][1]);
    }

    public function test_syncFields_new_field_creates_definition_and_row()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);

        $changed = $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);

        $this->assertTrue($changed);
        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'material',
        ]);
        $this->assertDatabaseHas('blueprint_fields', [
            'blueprint_id' => $bp->id,
        ]);
    }

    public function test_sync_fields_idempotent()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $fields = [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ];

        $this->presetService->syncFields($bp, $this->shop, $fields);
        $changed = $this->presetService->syncFields($bp, $this->shop, $fields);

        $this->assertFalse($changed);
    }

    public function test_sync_fields_removes_missing_field()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);

        $changed = $this->presetService->syncFields($bp, $this->shop, []);

        $this->assertTrue($changed);
        $this->assertDatabaseMissing('blueprint_fields', [
            'blueprint_id' => $bp->id,
        ]);
        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'material',
        ]);
    }

    public function test_sync_fields_unshared_field_can_change_type()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);

        $changed = $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'number'],
        ]);

        $this->assertTrue($changed);
        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'material',
            'type' => 'number',
        ]);
    }

    public function test_sync_fields_shared_field_same_type_accepted()
    {
        $bp1 = $this->presetService->createGenericBlueprint($this->shop);
        $bp2 = $this->presetService->createGenericBlueprint($this->shop);

        $this->presetService->syncFields($bp1, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);

        $changed = $this->presetService->syncFields($bp2, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);

        // Should return true because it creates the blueprint_field row
        $this->assertTrue($changed);
    }

    public function test_sync_fields_shared_field_different_type_on_existing_blueprint_aborts()
    {
        $bp1 = $this->presetService->createGenericBlueprint($this->shop);
        $bp2 = $this->presetService->createFromPreset($this->shop, 'fashion');

        $this->presetService->syncFields($bp1, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);

        // Now sync to bp2 to make it shared
        $this->presetService->syncFields($bp2, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('shared with another blueprint');

        $this->presetService->syncFields($bp1, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'number'],
        ]);
    }

    public function test_sync_fields_shared_field_type_abort_leaves_definition_unchanged()
    {
        $bp1 = $this->presetService->createGenericBlueprint($this->shop);
        $bp2 = $this->presetService->createFromPreset($this->shop, 'fashion');

        $this->presetService->syncFields($bp1, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);
        $this->presetService->syncFields($bp2, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);

        try {
            $this->presetService->syncFields($bp1, $this->shop, [
                ['key' => 'material', 'label' => 'Material', 'type' => 'number'],
            ]);
            $this->fail('Changing the type of a shared field should abort.');
        } catch (HttpException $e) {
            $this->assertStringContainsString('shared with another blueprint', $e->getMessage());
        }

        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'material',
            'type' => 'text',
        ]);
    }

    public function test_sync_fields_shared_field_different_type_on_new_blueprint_saves()
    {
        $bp1 = $this->presetService->createGenericBlueprint($this->shop);
        $bp2 = $this->presetService->createFromPreset($this->shop, 'cafe');

        $this->presetService->syncFields($bp1, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
        ]);

        $changed = $this->presetService->syncFields($bp2, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'number'],
        ]);

        // Should return true because it creates the blueprint_field row, but type stays text
        $this->assertTrue($changed);
        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'material',
            'type' => 'text',
        ]);
    }

    public function test_sync_fields_shared_field_label_and_options_edits_are_ignored()
    {
        $bp1 = $this->presetService->createGenericBlueprint($this->shop);
        $bp2 = $this->presetService->createFromPreset($this->shop, 'cafe');
        $options = [['value' => 'cotton', 'label' => 'Cotton', 'active' => true]];

        $this->presetService->syncFields($bp1, $this->shop, [
            ['key' => 'material', 'label' => 'Material', 'type' => 'select', 'options' => $options],
        ]);
        $this->presetService->syncFields($bp2, $this->shop, [
            ['key' => 'material', 'label' => 'Renamed', 'type' => 'select', 'options' => [
                ['value' => 'linen', 'label' => 'Linen', 'active' => true],
            ]],
        ]);

        $def = FieldDefinition::where('shop_owner_id', $this->shop->id)
            ->where('key', 'material')
            ->first();

        $this->assertSame('Material', $def->label);
        $this->assertSame($options, $def->options);
    }

    public function test_field_added_via_blueprint_update_appears_in_form_and_saves_on_product()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);

        $this->actingAs($this->shopOwner)->putJson("/api/blueprints/{$bp->id}", [
            'fields' => [['key' => 'origin', 'label' => 'Origin', 'type' => 'text']],
        ])->assertOk();

        $form = $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$bp->id}/form")
            ->assertOk();
        $this->assertSame('origin', $form->json('sections.0.fields.0.key'));

        $unit = \App\Models\Unit::factory()->create();
        $this->actingAs($this->shopOwner)->postJson('/api/products', [
            'name' => 'P',
            'selling_price' => '10',
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
            'custom_fields' => ['origin' => 'Myanmar'],
        ])->assertCreated();

        $this->assertSame('Myanmar', Product::latest('id')->first()->custom_fields['origin']);
    }

    public function test_field_added_via_wizard_shaped_update_appears_in_form()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);

        $this->actingAs($this->shopOwner)->putJson("/api/blueprints/{$bp->id}", [
            'name' => 'Generic retail',
            'description' => 'Updated through the wizard',
            'capabilities' => [
                'variants' => false,
                'bundles' => false,
                'batch_expiry' => false,
                'serial_numbers' => false,
                'decimal_quantities' => true,
            ],
            'pricing_policy' => [
                'allowed_modes' => ['fixed', 'negotiable'],
                'default_mode' => 'fixed',
                'min_margin_percent' => null,
                'cost_required' => false,
                'block_below_margin' => false,
                'price_per_variant' => false,
                'pos_price_override' => false,
            ],
            'unit_policy' => [
                'default_base_unit_id' => null,
                'allowed_unit_ids' => [],
                'price_per_unit' => true,
                'barcode_per_unit' => false,
                'buy_sell_different_units' => true,
            ],
            'fields' => [[
                '_id' => 'client-origin-id',
                '_new' => true,
                'key' => 'origin',
                'label' => 'Origin',
                'type' => 'text',
                'section' => 'details',
                'sort_order' => 0,
                'required' => false,
                'show_in_list' => true,
                'show_in_pos' => false,
                'show_on_label' => false,
                'is_variant_axis' => false,
                'is_filterable' => false,
                'is_searchable' => true,
                'hidden' => false,
            ]],
            'system_fields' => [
                'description' => ['hidden' => false, 'required' => false],
                'brand_id' => ['hidden' => false, 'required' => false],
            ],
        ])->assertOk();

        $form = $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$bp->id}/form")
            ->assertOk();

        $this->assertSame('origin', $form->json('sections.0.fields.0.key'));
        $this->assertDatabaseHas('blueprint_fields', [
            'blueprint_id' => $bp->id,
            'hidden' => false,
            'is_variant_axis' => false,
        ]);
    }

    public function test_hidden_custom_field_is_absent_from_form()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);

        $this->actingAs($this->shopOwner)->putJson("/api/blueprints/{$bp->id}", [
            'fields' => [['key' => 'origin', 'label' => 'Origin', 'type' => 'text', 'hidden' => true]],
        ])->assertOk();

        $form = $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$bp->id}/form")
            ->assertOk();

        $this->assertSame([], $form->json('sections'));
    }

    public function test_select_custom_field_appears_with_options_and_saves()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);

        $this->actingAs($this->shopOwner)->putJson("/api/blueprints/{$bp->id}", [
            'fields' => [[
                'key' => 'origin',
                'label' => 'Origin',
                'type' => 'select',
                'options' => [
                    ['value' => 'myanmar', 'label' => 'Myanmar', 'active' => true],
                    ['value' => 'thailand', 'label' => 'Thailand', 'active' => true],
                ],
            ]],
        ])->assertOk();

        $form = $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$bp->id}/form")
            ->assertOk();
        $this->assertSame('myanmar', $form->json('sections.0.fields.0.options.0.value'));

        $unit = \App\Models\Unit::factory()->create();
        $this->actingAs($this->shopOwner)->postJson('/api/products', [
            'name' => 'P',
            'selling_price' => '10',
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
            'custom_fields' => ['origin' => 'myanmar'],
        ])->assertCreated();

        $this->assertSame('myanmar', Product::latest('id')->first()->custom_fields['origin']);
    }

    public function test_blueprint_update_with_fields_persists_and_bumps_version()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);

        $this->actingAs($this->shopOwner)->putJson("/api/blueprints/{$bp->id}", [
            'fields' => [['key' => 'origin', 'label' => 'Origin', 'type' => 'text']],
        ])->assertOk();

        $this->assertSame(2, $bp->fresh()->version);
        $this->assertDatabaseHas('field_definitions', [
            'shop_owner_id' => $this->shop->id,
            'key' => 'origin',
        ]);
    }

    public function test_blueprint_update_with_only_name_leaves_fields_and_version_alone()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'origin', 'label' => 'Origin', 'type' => 'text'],
        ]);

        $this->actingAs($this->shopOwner)->putJson("/api/blueprints/{$bp->id}", [
            'name' => 'Renamed',
        ])->assertOk();

        $bp->refresh();
        $this->assertSame('Renamed', $bp->name);
        $this->assertSame(1, $bp->version);
        $this->assertCount(1, $bp->fields);
    }

    public function test_blueprint_update_with_system_fields_sanitizes_and_bumps_version()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);

        $this->actingAs($this->shopOwner)->putJson("/api/blueprints/{$bp->id}", [
            'system_fields' => [
                'description' => ['hidden' => true],
                'name' => ['hidden' => true, 'required' => true],
                'unknown' => ['hidden' => true],
            ],
        ])->assertOk();

        $bp->refresh();
        $this->assertSame(2, $bp->version);
        $this->assertSame(['description' => ['hidden' => true, 'required' => false]], $bp->system_fields);
    }

    public function test_required_custom_field_blocks_product_create()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'origin', 'label' => 'Origin', 'type' => 'text', 'required' => true],
        ]);
        $unit = \App\Models\Unit::factory()->create();

        $response = $this->actingAs($this->shopOwner)->postJson('/api/products', [
            'name' => 'P',
            'selling_price' => '10',
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
            'custom_fields' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('custom_fields.origin');
    }

    public function test_checkbox_custom_field_accepts_true()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'taxable', 'label' => 'Taxable', 'type' => 'checkbox'],
        ]);
        $unit = \App\Models\Unit::factory()->create();

        $this->actingAs($this->shopOwner)->postJson('/api/products', [
            'name' => 'P',
            'selling_price' => '10',
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
            'custom_fields' => ['taxable' => true],
        ])->assertCreated();

        $this->assertTrue(Product::latest('id')->first()->custom_fields['taxable']);
    }

    public function test_json_custom_field_accepts_array()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'specs', 'label' => 'Specs', 'type' => 'json'],
        ]);
        $unit = \App\Models\Unit::factory()->create();

        $this->actingAs($this->shopOwner)->postJson('/api/products', [
            'name' => 'P',
            'selling_price' => '10',
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
            'custom_fields' => ['specs' => ['origin' => 'Myanmar']],
        ])->assertCreated();

        $this->assertSame(['origin' => 'Myanmar'], Product::latest('id')->first()->custom_fields['specs']);
    }

    public function test_decimal_custom_field_accepts_number()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'weight', 'label' => 'Weight', 'type' => 'decimal'],
        ]);
        $unit = \App\Models\Unit::factory()->create();

        $this->actingAs($this->shopOwner)->postJson('/api/products', [
            'name' => 'P',
            'selling_price' => '10',
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
            'custom_fields' => ['weight' => 1.25],
        ])->assertCreated();

        $this->assertSame(1.25, Product::latest('id')->first()->custom_fields['weight']);
    }

    public function test_textarea_custom_field_accepts_over_255_characters()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        $this->presetService->syncFields($bp, $this->shop, [
            ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ]);
        $unit = \App\Models\Unit::factory()->create();
        $long = str_repeat('a', 300);

        $this->actingAs($this->shopOwner)->postJson('/api/products', [
            'name' => 'P',
            'selling_price' => '10',
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
            'custom_fields' => ['notes' => $long],
        ])->assertCreated();

        $this->assertSame($long, Product::latest('id')->first()->custom_fields['notes']);
    }

    public function test_cost_required_is_enforced_even_when_cost_price_marked_hidden()
    {
        $bp = Blueprint::factory()->for($this->shop)->create([
            'pricing_policy' => ['cost_required' => true],
            'system_fields' => ['cost_price' => ['hidden' => true]],
        ]);
        $unit = \App\Models\Unit::factory()->create();

        $response = $this->actingAs($this->shopOwner)->postJson('/api/products', [
            'name' => 'P',
            'selling_price' => '10',
            'unit_id' => $unit->id,
            'blueprint_id' => $bp->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cost_price');
    }

    public function test_another_shops_blueprint_form_returns_404()
    {
        $otherShop = ShopOwner::factory()->create();
        $bp = $this->presetService->createGenericBlueprint($otherShop);

        $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$bp->id}/form")
            ->assertNotFound();
    }

    public function test_staff_gets_403_on_blueprint_store_and_update()
    {
        $staffUser = User::factory()->create(['role' => 'staff']);
        Staff::factory()->for($this->shop)->for($staffUser)->create(['status' => 'active']);
        $bp = $this->presetService->createGenericBlueprint($this->shop);

        $this->actingAs($staffUser)->postJson('/api/blueprints', [
            'name' => 'Staff Blueprint',
        ])->assertForbidden();

        $this->actingAs($staffUser)->putJson("/api/blueprints/{$bp->id}", [
            'name' => 'Staff Rename',
        ])->assertForbidden();
    }

    public function test_presets_returns_system_fields()
    {
        $response = $this->actingAs($this->shopOwner)
            ->getJson('/api/blueprints/presets');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'presets',
            'system_fields',
        ]);
        $this->assertIsArray($response->json('system_fields'));
    }

    public function test_blueprint_form_returns_system_fields()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);

        $response = $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$bp->id}/form");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'system_fields',
        ]);
    }

    public function test_blueprint_show_includes_products_count()
    {
        $bp = $this->presetService->createGenericBlueprint($this->shop);
        for ($i = 0; $i < 3; $i++) {
            Product::factory()->for($this->shop)->create(['blueprint_id' => $bp->id]);
        }

        $response = $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$bp->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('blueprint.products_count', 3);
    }
}
