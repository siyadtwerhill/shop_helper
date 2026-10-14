<?php

namespace App\Services;

use App\Models\Blueprint;

/**
 * Registry of built-in product fields.
 *
 * Keys match the request/validation keys in ProductController exactly.
 * Only `hidden` and `required` overrides are stored per-blueprint in
 * the `system_fields` JSON column; defaults come from this file.
 */
class ProductSystemFields
{
    /** @var array<string, array{label:string, section:string, can_hide:bool, can_require:bool}> */
    public const FIELDS = [
        'name'           => ['label' => 'Name',           'section' => 'details',   'can_hide' => false, 'can_require' => false], // always required
        'category_id'    => ['label' => 'Category',       'section' => 'details',   'can_hide' => true,  'can_require' => true],
        'brand_id'       => ['label' => 'Brand',          'section' => 'details',   'can_hide' => true,  'can_require' => true],
        'description'    => ['label' => 'Description',    'section' => 'details',   'can_hide' => true,  'can_require' => true],
        'internal_notes' => ['label' => 'Internal notes', 'section' => 'details',   'can_hide' => true,  'can_require' => true],
        'image'          => ['label' => 'Image',          'section' => 'details',   'can_hide' => true,  'can_require' => true],
        'cost_price'     => ['label' => 'Cost price',     'section' => 'pricing',   'can_hide' => true,  'can_require' => true],
        'min_price'      => ['label' => 'Minimum price',  'section' => 'pricing',   'can_hide' => true,  'can_require' => false], // only shown for price_range mode
        'min_stock'      => ['label' => 'Minimum stock',  'section' => 'inventory', 'can_hide' => true,  'can_require' => true],
    ];

    /**
     * Registry merged with a blueprint's overrides.
     * Returns keyed array: ['name' => [...], 'category_id' => [...], ...]
     */
    public static function resolve(Blueprint $bp): array
    {
        $over = $bp->system_fields ?? [];
        $out  = [];

        foreach (self::FIELDS as $key => $def) {
            $hidden   = $def['can_hide']    && ! empty($over[$key]['hidden']);
            $required = $def['can_require'] && ! $hidden && ! empty($over[$key]['required']);

            // When the pricing policy already mandates cost, the field can't be hidden or freed.
            if ($key === 'cost_price' && ! empty($bp->pricing_policy['cost_required'])) {
                $hidden   = false;
                $required = true;
            }

            $out[$key] = ['key' => $key] + $def + ['hidden' => $hidden, 'required' => $required];
        }

        return $out;
    }

    /**
     * Drop unknown keys and clamp to what each field allows.
     * Call this before persisting to system_fields.
     */
    public static function sanitize(?array $input): array
    {
        $clean = [];

        foreach (self::FIELDS as $key => $def) {
            if (! isset($input[$key])) continue;

            $hidden   = $def['can_hide']    && ! empty($input[$key]['hidden']);
            $required = $def['can_require'] && ! $hidden && ! empty($input[$key]['required']);

            if ($hidden || $required) {
                $clean[$key] = ['hidden' => $hidden, 'required' => $required];
            }
        }

        return $clean;
    }
}
