<?php

namespace App\Services;

use App\Models\Blueprint;
use App\Models\BlueprintField;
use Illuminate\Support\Collection;

class BlueprintSchemaResolver
{
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
                    'label' => ucfirst($key),
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
