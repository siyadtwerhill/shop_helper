<?php

namespace Tests\Feature;

use App\Models\Blueprint;
use App\Models\BlueprintField;
use App\Models\ShopOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlueprintTest extends TestCase
{
    use RefreshDatabase;

    private User $shopOwner;
    private ShopOwner $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shopOwner = User::factory()->create(['role' => 'shop_owner']);
        $this->shop = ShopOwner::factory()->create();
        $this->shop->user()->associate($this->shopOwner);
        $this->shop->save();
    }

    public function test_shop_owner_can_list_blueprints()
    {
        $blueprint = Blueprint::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'name' => 'Fashion Retail',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->getJson('/api/blueprints');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'blueprints' => [
                    '*' => [
                        'id',
                        'name',
                        'preset_key',
                        'is_default',
                        'status',
                        'capabilities',
                        'pricing_policy',
                        'unit_policy',
                        'layout',
                        'version',
                        'fields_count',
                    ],
                ],
            ])
            ->assertJsonCount(1, 'blueprints');
    }

    public function test_shop_owner_can_create_blueprint()
    {
        $data = [
            'name' => 'Fashion Retail',
            'preset_key' => 'fashion',
            'capabilities' => [
                'variants' => true,
                'bundles' => false,
                'multiple_units' => true,
            ],
            'pricing_policy' => [
                'allowed_modes' => ['fixed', 'negotiable'],
                'default_mode' => 'fixed',
                'min_margin_percent' => 15,
            ],
            'unit_policy' => [
                'default_base_unit' => 'piece',
                'allowed_units' => ['piece', 'box'],
            ],
        ];

        $response = $this->actingAs($this->shopOwner)
            ->postJson('/api/blueprints', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'blueprint' => [
                    'id',
                    'name',
                    'preset_key',
                    'capabilities',
                    'pricing_policy',
                    'unit_policy',
                ],
            ]);

        $this->assertDatabaseHas('blueprints', [
            'name' => 'Fashion Retail',
            'shop_owner_id' => $this->shop->id,
        ]);
    }

    public function test_shop_owner_can_create_blueprint_with_fields()
    {
        $data = [
            'name' => 'Fashion Retail',
            'fields' => [
                [
                    'key' => 'material',
                    'label' => 'Material',
                    'type' => 'text',
                    'section' => 'details',
                    'required' => false,
                    'show_in_list' => true,
                ],
                [
                    'key' => 'color',
                    'label' => 'Color',
                    'type' => 'select',
                    'options' => ['Black', 'White', 'Navy'],
                    'section' => 'variant_axes',
                    'required' => true,
                    'is_variant_axis' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->shopOwner)
            ->postJson('/api/blueprints', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('field_definitions', [
            'key' => 'material',
            'label' => 'Material',
        ]);

        $this->assertDatabaseHas('field_definitions', [
            'key' => 'color',
            'label' => 'Color',
        ]);

        $this->assertDatabaseHas('blueprint_fields', [
            'section' => 'details',
            'required' => false,
        ]);

        $this->assertDatabaseHas('blueprint_fields', [
            'section' => 'variant_axes',
            'required' => true,
            'is_variant_axis' => true,
        ]);
    }

    public function test_shop_owner_can_view_blueprint()
    {
        $blueprint = Blueprint::factory()
            ->has(BlueprintField::factory()->count(2), 'fields')
            ->create([
                'shop_owner_id' => $this->shop->id,
            ]);

        $response = $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$blueprint->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'blueprint' => [
                    'id',
                    'name',
                    'fields' => [
                        '*' => [
                            'id',
                            'field_definition' => [
                                'id',
                                'key',
                                'label',
                                'type',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_shop_owner_can_update_blueprint()
    {
        $blueprint = Blueprint::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->putJson("/api/blueprints/{$blueprint->id}", [
                'name' => 'Updated Name',
                'status' => 'active',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Blueprint updated.',
            ]);

        $this->assertDatabaseHas('blueprints', [
            'id' => $blueprint->id,
            'name' => 'Updated Name',
            'status' => 'active',
        ]);
    }

    public function test_shop_owner_can_delete_blueprint()
    {
        $blueprint = Blueprint::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->deleteJson("/api/blueprints/{$blueprint->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Blueprint deleted.',
            ]);

        $this->assertSoftDeleted('blueprints', [
            'id' => $blueprint->id,
        ]);
    }

    public function test_cannot_delete_default_blueprint()
    {
        $blueprint = Blueprint::factory()->create([
            'shop_owner_id' => $this->shop->id,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->deleteJson("/api/blueprints/{$blueprint->id}");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Cannot delete the default blueprint.',
            ]);
    }

    public function test_shop_owner_can_duplicate_blueprint()
    {
        $blueprint = Blueprint::factory()
            ->has(BlueprintField::factory()->count(2), 'fields')
            ->create([
                'shop_owner_id' => $this->shop->id,
                'name' => 'Original',
            ]);

        $response = $this->actingAs($this->shopOwner)
            ->postJson("/api/blueprints/{$blueprint->id}/duplicate");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'blueprint' => [
                    'id',
                    'name',
                    'fields',
                ],
            ]);

        $this->assertDatabaseHas('blueprints', [
            'name' => 'Original (copy)',
            'shop_owner_id' => $this->shop->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseCount('blueprint_fields', 4); // 2 original + 2 duplicated
    }

    public function test_can_get_blueprint_schema()
    {
        $blueprint = Blueprint::factory()
            ->has(BlueprintField::factory()->count(2), 'fields')
            ->create([
                'shop_owner_id' => $this->shop->id,
            ]);

        $response = $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$blueprint->id}/schema");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'blueprint',
                'schema' => [
                    'capabilities',
                    'pricing_policy',
                    'unit_policy',
                    'layout',
                    'fields' => [
                        '*' => [
                            'key',
                            'label',
                            'type',
                            'section',
                            'required',
                            'is_variant_axis',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_get_available_presets()
    {
        $response = $this->actingAs($this->shopOwner)
            ->getJson('/api/blueprints/presets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'presets' => [
                    '*' => [
                        'key',
                        'name',
                        'description',
                        'icon',
                    ],
                ],
            ]);
    }

    public function test_cannot_access_another_shops_blueprint()
    {
        $otherShop = ShopOwner::factory()->create();
        $blueprint = Blueprint::factory()->create([
            'shop_owner_id' => $otherShop->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->getJson("/api/blueprints/{$blueprint->id}");

        $response->assertStatus(404);
    }

    public function test_cannot_update_another_shops_blueprint()
    {
        $otherShop = ShopOwner::factory()->create();
        $blueprint = Blueprint::factory()->create([
            'shop_owner_id' => $otherShop->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->putJson("/api/blueprints/{$blueprint->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_delete_another_shops_blueprint()
    {
        $otherShop = ShopOwner::factory()->create();
        $blueprint = Blueprint::factory()->create([
            'shop_owner_id' => $otherShop->id,
        ]);

        $response = $this->actingAs($this->shopOwner)
            ->deleteJson("/api/blueprints/{$blueprint->id}");

        $response->assertStatus(404);
    }
}
