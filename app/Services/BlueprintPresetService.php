<?php

namespace App\Services;

use App\Models\Blueprint;
use App\Models\BlueprintField;
use App\Models\FieldDefinition;
use App\Models\ShopOwner;

class BlueprintPresetService
{
    private const ALL_MODES = ['fixed', 'negotiable', 'price_range', 'wholesale'];

    private function opts(array $labels): array
    {
        return array_map(fn ($l) => [
            'value' => strtolower(str_replace(' ', '_', $l)), 'label' => $l, 'active' => true,
        ], $labels);
    }

    private function field(array $o): array
    {
        return $o + [
            'type' => 'text', 'options' => null, 'section' => 'details', 'sort_order' => 0,
            'required' => false, 'show_in_list' => false, 'show_in_pos' => false,
            'show_on_label' => false, 'is_variant_axis' => false,
            'is_filterable' => false, 'is_searchable' => false, 'hidden' => false,
        ];
    }

    private function caps(array $on = []): array
    {
        return array_merge([
            'variants' => false, 'bundles' => false, 'batch_expiry' => false,
            'serial_numbers' => false, 'decimal_quantities' => false,
        ], $on);
    }

    private function pricing(array $o = []): array
    {
        return array_merge([
            'allowed_modes' => self::ALL_MODES, 'default_mode' => 'fixed',
            'min_margin_percent' => null, 'cost_required' => false,
            'block_below_margin' => false, 'price_per_variant' => false,
            'pos_price_override' => false,
        ], $o);
    }

    private function units(): array
    {
        return [
            'default_base_unit_id' => null, 'allowed_unit_ids' => [],
            'price_per_unit' => true, 'barcode_per_unit' => false,
            'buy_sell_different_units' => true,
        ];
    }

    public function definitions(): array
    {
        $f = fn (array $o) => $this->field($o);

        return [
            'generic' => [
                'key' => 'generic', 'name' => 'Generic', 'icon' => 'Package',
                'description' => 'Simple products without variants or bundles',
                'capabilities' => $this->caps(['decimal_quantities' => true]),
                'pricing_policy' => $this->pricing(),
                'unit_policy' => $this->units(),
                'fields' => [],
            ],
            'fashion' => [
                'key' => 'fashion', 'name' => 'Fashion', 'icon' => 'Shirt',
                'description' => 'Clothing and accessories with size/color variants',
                'capabilities' => $this->caps(['variants' => true]),
                'pricing_policy' => $this->pricing(['min_margin_percent' => 15, 'cost_required' => true, 'price_per_variant' => true]),
                'unit_policy' => $this->units(),
                'fields' => [
                    $f(['key' => 'material', 'label' => 'Material', 'sort_order' => 1, 'show_in_list' => true, 'is_filterable' => true, 'is_searchable' => true]),
                    $f(['key' => 'season', 'label' => 'Season', 'type' => 'select', 'options' => $this->opts(['Spring', 'Summer', 'Fall', 'Winter']), 'sort_order' => 2, 'show_in_list' => true, 'is_filterable' => true]),
                    $f(['key' => 'color', 'label' => 'Color', 'type' => 'select', 'options' => $this->opts(['Black', 'White', 'Navy', 'Red', 'Blue', 'Green']), 'section' => 'variant_axes', 'sort_order' => 1, 'required' => true, 'is_variant_axis' => true, 'show_in_list' => true, 'show_in_pos' => true, 'show_on_label' => true, 'is_filterable' => true]),
                    $f(['key' => 'size', 'label' => 'Size', 'type' => 'select', 'options' => $this->opts(['XS', 'S', 'M', 'L', 'XL', 'XXL']), 'section' => 'variant_axes', 'sort_order' => 2, 'required' => true, 'is_variant_axis' => true, 'show_in_list' => true, 'show_in_pos' => true, 'show_on_label' => true, 'is_filterable' => true]),
                ],
            ],
            'grocery' => [
                'key' => 'grocery', 'name' => 'Grocery', 'icon' => 'ShoppingCart',
                'description' => 'Food items with expiry dates and batch tracking',
                'capabilities' => $this->caps(['batch_expiry' => true, 'decimal_quantities' => true]),
                'pricing_policy' => $this->pricing(['allowed_modes' => ['fixed', 'wholesale'], 'min_margin_percent' => 10, 'cost_required' => true]),
                'unit_policy' => $this->units(),
                'fields' => [
                    $f(['key' => 'storage', 'label' => 'Storage', 'type' => 'select', 'options' => $this->opts(['Ambient', 'Chilled', 'Frozen']), 'sort_order' => 1, 'show_on_label' => true, 'is_filterable' => true]),
                    $f(['key' => 'origin', 'label' => 'Origin', 'sort_order' => 2]),
                ],
            ],
            'pharmacy' => [
                'key' => 'pharmacy', 'name' => 'Pharmacy', 'icon' => 'Pill',
                'description' => 'Medicines with serial numbers and expiry tracking',
                'capabilities' => $this->caps(['batch_expiry' => true, 'serial_numbers' => true]),
                'pricing_policy' => $this->pricing(['allowed_modes' => ['fixed'], 'min_margin_percent' => 10, 'cost_required' => true, 'block_below_margin' => true]),
                'unit_policy' => $this->units(),
                'fields' => [
                    $f(['key' => 'generic_name', 'label' => 'Generic name', 'sort_order' => 1, 'show_in_list' => true, 'is_searchable' => true]),
                    $f(['key' => 'dosage_form', 'label' => 'Dosage form', 'type' => 'select', 'options' => $this->opts(['Tablet', 'Capsule', 'Syrup', 'Injection', 'Cream']), 'sort_order' => 2, 'is_filterable' => true]),
                    $f(['key' => 'strength', 'label' => 'Strength', 'sort_order' => 3, 'show_on_label' => true]),
                ],
            ],
            'electronics' => [
                'key' => 'electronics', 'name' => 'Electronics', 'icon' => 'Cpu',
                'description' => 'Tech products with serial numbers and warranties',
                'capabilities' => $this->caps(['serial_numbers' => true]),
                'pricing_policy' => $this->pricing(['min_margin_percent' => 10, 'cost_required' => true]),
                'unit_policy' => $this->units(),
                'fields' => [
                    $f(['key' => 'model', 'label' => 'Model', 'sort_order' => 1, 'show_in_list' => true, 'is_searchable' => true]),
                    $f(['key' => 'warranty_months', 'label' => 'Warranty (months)', 'type' => 'number', 'sort_order' => 2, 'show_on_label' => true]),
                ],
            ],
            'cafe' => [
                'key' => 'cafe', 'name' => 'Cafe', 'icon' => 'Coffee',
                'description' => 'Food and beverages with decimal quantities',
                'capabilities' => $this->caps(['decimal_quantities' => true]),
                'pricing_policy' => $this->pricing(['allowed_modes' => ['fixed']]),
                'unit_policy' => $this->units(),
                'fields' => [
                    $f(['key' => 'serving_size', 'label' => 'Serving size', 'sort_order' => 1, 'show_in_pos' => true]),
                    $f(['key' => 'is_hot', 'label' => 'Served hot', 'type' => 'boolean', 'sort_order' => 2, 'show_in_pos' => true]),
                ],
            ],
        ];
    }

    public function getAvailablePresets(): array
    {
        return array_values($this->definitions());
    }

    /** Create a blueprint for a shop from a preset key (used at shop registration, etc.). */
    public function createFromPreset(ShopOwner $shop, string $key, array $attrs = []): Blueprint
    {
        $def = $this->definitions()[$key] ?? abort(422, "Unknown preset: {$key}");

        $bp = Blueprint::create($attrs + [
            'shop_owner_id' => $shop->id,
            'name' => $def['name'],
            'preset_key' => $key,
            'is_default' => false,
            'status' => 'active',
            'capabilities' => $def['capabilities'],
            'pricing_policy' => $def['pricing_policy'],
            'unit_policy' => $def['unit_policy'],
            'layout' => [],
            'version' => 1,
        ]);

        $this->syncFields($bp, $shop, $def['fields']);

        return $bp;
    }

    public function createGenericBlueprint(ShopOwner $shop): Blueprint
    {
        return $this->createFromPreset($shop, 'generic', ['is_default' => true]);
    }

    public function createFashionBlueprint(ShopOwner $shop): Blueprint
    {
        return $this->createFromPreset($shop, 'fashion', ['name' => 'Fashion Retail']);
    }

    /**
     * Make the blueprint's fields match $fields exactly.
     * Returns true if anything was added, changed or removed (caller bumps version).
     */
    public function syncFields(Blueprint $bp, ShopOwner $shop, array $fields): bool
    {
        $changed = false;
        $keep = [];

        foreach (array_values($fields) as $i => $f) {
            $def = FieldDefinition::firstOrCreate(
                ['shop_owner_id' => $shop->id, 'key' => $f['key']],
                ['label' => $f['label'], 'type' => $f['type'],
                 'options' => $f['options'] ?? null, 'validation' => $f['validation'] ?? null]
            );

            $current = $def->type instanceof \BackedEnum ? $def->type->value : (string) $def->type;
            $shared = BlueprintField::where('field_definition_id', $def->id)
                ->where('blueprint_id', '!=', $bp->id)->exists();

            if (! $def->wasRecentlyCreated) {
                if ($shared) {
                    // Another blueprint uses this definition, so it is read-only from here.
                    abort_if(
                        $current !== $f['type'] && $bp->fields()->where('field_definition_id', $def->id)->exists(),
                        422,
                        "Field \"{$f['key']}\" is shared with another blueprint, so its type can't change."
                    );
                } else {
                    $def->label = $f['label'];
                    $def->options = $f['options'] ?? $def->options;
                    if ($current !== $f['type']) $def->type = $f['type'];
                    if ($def->isDirty()) { $def->save(); $changed = true; }
                }
            }

            $row = BlueprintField::firstOrNew([
                'blueprint_id' => $bp->id, 'field_definition_id' => $def->id,
            ]);
            $row->fill([
                'section'         => $f['section'] ?? 'details',
                'sort_order'      => $f['sort_order'] ?? $i,
                'required'        => $f['required'] ?? false,
                'show_in_list'    => $f['show_in_list'] ?? false,
                'show_in_pos'     => $f['show_in_pos'] ?? false,
                'show_on_label'   => $f['show_on_label'] ?? false,
                'is_variant_axis' => $f['is_variant_axis'] ?? false,
                'is_filterable'   => $f['is_filterable'] ?? false,
                'is_searchable'   => $f['is_searchable'] ?? false,
                'hidden'          => $f['hidden'] ?? false,
            ]);

            if (! $row->exists || $row->isDirty()) $changed = true;
            $row->save();
            $keep[] = $row->id;
        }

        $removed = $bp->fields()->whereNotIn('id', $keep)->delete();

        return $changed || $removed > 0;
    }

    /** Keeps Color/Size axes present when variants are on (unchanged behaviour). */
    public function ensureVariantAxes(Blueprint $bp): void
    {
        $order = BlueprintField::where('blueprint_id', $bp->id)->where('is_variant_axis', true)->count();

        foreach (['color' => 'Color', 'size' => 'Size'] as $key => $label) {
            $def = FieldDefinition::firstOrCreate(
                ['shop_owner_id' => $bp->shop_owner_id, 'key' => $key],
                ['label' => $label, 'type' => 'text', 'options' => null, 'validation' => null]
            );
            BlueprintField::firstOrCreate(
                ['blueprint_id' => $bp->id, 'field_definition_id' => $def->id],
                [
                    'section' => 'variant_axes', 'sort_order' => ++$order, 'required' => false,
                    'is_variant_axis' => true, 'show_in_list' => false, 'show_in_pos' => true,
                    'show_on_label' => true, 'is_filterable' => false, 'is_searchable' => false,
                    'hidden' => false,
                ]
            );
        }
    }
}
