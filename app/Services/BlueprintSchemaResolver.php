<?php

namespace App\Services;

use App\Models\Blueprint;
use App\Models\BlueprintField;
use App\Services\ProductSystemFields;
use Illuminate\Support\Collection;

class BlueprintSchemaResolver
{
    /** What the product form needs: custom-field sections (no variant axes, no hidden fields) and the axes separately. */
    public function formFor(Blueprint $blueprint): array
    {
        $blueprint->load('fields.fieldDefinition');

        $fields = $blueprint->fields
            ->filter(fn (BlueprintField $f) => ! $f->hidden && $f->fieldDefinition)
            ->sortBy('sort_order')
            ->values();

        $layout = $blueprint->layout['sections'] ?? [];
        $labels = [
            'details'    => 'Details',
            'pricing'    => 'Pricing',
            'inventory'  => 'Inventory',
            'attributes' => 'Attributes',
            'custom'     => 'Custom Fields',
        ];

        $sections = $fields
            ->reject(fn ($f) => $f->is_variant_axis)
            ->groupBy('section')
            ->map(fn ($group, $key) => [
                'key'    => $key,
                'label'  => $layout[$key]['label'] ?? $labels[$key] ?? ucwords(str_replace('_', ' ', $key)),
                'sort'   => $layout[$key]['sort_order'] ?? 99,
                'fields' => $group->map(fn ($f) => [
                    'key'      => $f->fieldDefinition->key,
                    'label'    => $f->fieldDefinition->label,
                    'type'     => (string) $f->fieldDefinition->type,
                    'options'  => $this->normalizeOptions($f->fieldDefinition->options ?? []),
                    'required' => (bool) $f->required,
                ])->values()->all(),
            ])
            ->sortBy('sort')
            ->values()
            ->map(fn ($s) => collect($s)->except('sort')->all())
            ->all();

        $axes = $fields
            ->filter(fn ($f) => $f->is_variant_axis)
            ->map(fn ($f) => [
                'key'      => $f->fieldDefinition->key,
                'label'    => $f->fieldDefinition->label,
                'required' => (bool) $f->required,
                'options'  => array_column($this->normalizeOptions($f->fieldDefinition->options ?? []), 'label'),
                'strict'   => (bool) (($f->fieldDefinition->validation ?? [])['strict'] ?? false),
            ])
            ->values()
            ->all();

        return [
            'blueprint'      => $blueprint->only(['id', 'name', 'version', 'is_default', 'status']),
            'capabilities'   => $blueprint->capabilities ?? [],
            'pricing_policy' => $blueprint->pricing_policy ?? [],
            'unit_policy'    => $blueprint->unit_policy ?? [],
            'sections'       => $sections,
            'axes'           => $axes,
            'system_fields'  => ProductSystemFields::resolve($blueprint),
        ];
    }

    /**
     * Plain strings and {value,label,active} objects both become {value,label}.
     * Inactive options are dropped.
     */
    private function normalizeOptions(array $options): array
    {
        return collect($options)->map(function ($o) {
            if (! is_array($o)) {
                return ['value' => (string) $o, 'label' => (string) $o];
            }
            if (! ($o['active'] ?? true)) return null;
            $v = (string) ($o['value'] ?? $o['label'] ?? '');
            return $v === '' ? null : ['value' => $v, 'label' => (string) ($o['label'] ?? $v)];
        })->filter()->values()->all();
    }

    /**
     * Resolve the complete schema for a blueprint
     */
    public function resolve(Blueprint $blueprint): array
    {
        $blueprint->load('fields.fieldDefinition');

        $sections = $this->groupFieldsBySection($blueprint->fields);

        return [
            'blueprint' => [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'preset_key' => $blueprint->preset_key,
                'version' => $blueprint->version,
                'is_default' => $blueprint->is_default,
                'status' => $blueprint->status,
            ],
            'capabilities' => $blueprint->capabilities ?? [],
            'pricing_policy' => $blueprint->pricing_policy ?? [],
            'unit_policy' => $blueprint->unit_policy ?? [],
            'layout' => $blueprint->layout ?? [],
            'sections' => $sections,
        ];
    }

    /**
     * Group fields by section for organized display
     */
    private function groupFieldsBySection(Collection $fields): array
    {
        $grouped = $fields->groupBy('section');

        $sectionOrder = [
            'details' => 'Product Details',
            'variant_axes' => 'Variant Axes',
            'pricing' => 'Pricing',
            'inventory' => 'Inventory',
            'attributes' => 'Attributes',
            'custom' => 'Custom Fields',
        ];

        $sections = [];

        foreach ($sectionOrder as $key => $label) {
            if ($grouped->has($key)) {
                $sections[] = [
                    'key' => $key,
                    'label' => $label,
                    'fields' => $this->formatFields($grouped->get($key)),
                ];
            }
        }

        // Add any sections not in the predefined order
        foreach ($grouped as $key => $sectionFields) {
            if (!isset($sectionOrder[$key])) {
                $sections[] = [
                    'key' => $key,
                    'label' => ucwords(str_replace('_', ' ', $key)),
                    'fields' => $this->formatFields($sectionFields),
                ];
            }
        }

        return $sections;
    }

    /**
     * Format fields for frontend consumption
     */
    private function formatFields(Collection $fields): array
    {
        return $fields->sortBy('sort_order')->map(function (BlueprintField $field) {
            $def = $field->fieldDefinition;

            return [
                'key' => $def->key,
                'label' => $def->label,
                'type' => $def->type,
                'options' => $def->options ?? [],
                'validation' => $def->validation ?? [],
                'section' => $field->section,
                'sort_order' => $field->sort_order,
                'required' => $field->required,
                'show_in_list' => $field->show_in_list,
                'show_in_pos' => $field->show_in_pos,
                'show_on_label' => $field->show_on_label,
                'is_variant_axis' => $field->is_variant_axis,
                'is_filterable' => $field->is_filterable,
                'is_searchable' => $field->is_searchable,
                'hidden' => $field->hidden,
            ];
        })->values()->toArray();
    }

    /**
     * Get variant axis fields from a blueprint
     */
    public function getVariantAxes(Blueprint $blueprint): array
    {
        return $blueprint->fields()
            ->where('is_variant_axis', true)
            ->with('fieldDefinition')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($field) {
                return [
                    'key' => $field->fieldDefinition->key,
                    'label' => $field->fieldDefinition->label,
                    'type' => $field->fieldDefinition->type,
                    'options' => $field->fieldDefinition->options ?? [],
                ];
            })
            ->toArray();
    }

    /**
     * Get fields that should be shown in the product list
     */
    public function getListFields(Blueprint $blueprint): array
    {
        return $blueprint->fields()
            ->where('show_in_list', true)
            ->where('hidden', false)
            ->with('fieldDefinition')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($field) {
                return [
                    'key' => $field->fieldDefinition->key,
                    'label' => $field->fieldDefinition->label,
                    'type' => $field->fieldDefinition->type,
                ];
            })
            ->toArray();
    }

    /**
     * Get fields that should be shown in POS
     */
    public function getPosFields(Blueprint $blueprint): array
    {
        return $blueprint->fields()
            ->where('show_in_pos', true)
            ->where('hidden', false)
            ->with('fieldDefinition')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($field) {
                return [
                    'key' => $field->fieldDefinition->key,
                    'label' => $field->fieldDefinition->label,
                    'type' => $field->fieldDefinition->type,
                ];
            })
            ->toArray();
    }

    /**
     * Get fields that should be shown on labels
     */
    public function getLabelFields(Blueprint $blueprint): array
    {
        return $blueprint->fields()
            ->where('show_on_label', true)
            ->where('hidden', false)
            ->with('fieldDefinition')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($field) {
                return [
                    'key' => $field->fieldDefinition->key,
                    'label' => $field->fieldDefinition->label,
                    'type' => $field->fieldDefinition->type,
                ];
            })
            ->toArray();
    }
}
