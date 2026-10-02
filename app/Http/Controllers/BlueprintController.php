<?php

namespace App\Http\Controllers;

use App\Models\Blueprint;
use App\Models\FieldDefinition;
use App\Models\BlueprintField;
use App\Services\BlueprintPresetService;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BlueprintController extends Controller
{
    use ResolvesShop;

    private BlueprintPresetService $presetService;

    public function __construct(BlueprintPresetService $presetService)
    {
        $this->presetService = $presetService;
    }

    /**
     * Get all blueprints for the current shop
     */
    public function index(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $blueprints = Blueprint::where('shop_owner_id', $shop->id)
            ->withCount('fields')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'blueprints' => $blueprints,
        ]);
    }

    /**
     * Get a single blueprint with fields
     */
    public function show(Request $request, Blueprint $blueprint): JsonResponse
    {
        $this->ownedBlueprint($request, $blueprint);

        $blueprint->load(['fields.fieldDefinition']);

        return response()->json([
            'blueprint' => $blueprint,
        ]);
    }

    /**
     * Create a new blueprint
     */
    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'preset_key' => 'nullable|string|in:fashion,grocery,pharmacy,electronics,cafe,blank',
            'capabilities' => 'nullable|array',
            'pricing_policy' => 'nullable|array',
            'unit_policy' => 'nullable|array',
            'fields' => 'nullable|array',
        ]);

        $blueprint = Blueprint::create([
            'shop_owner_id' => $shop->id,
            'name' => $data['name'],
            'preset_key' => $data['preset_key'] ?? null,
            'is_default' => false,
            'status' => 'active',
            'capabilities' => $data['capabilities'] ?? [],
            'pricing_policy' => $data['pricing_policy'] ?? [],
            'unit_policy' => $data['unit_policy'] ?? [],
            'version' => 1,
        ]);

        // Create fields if provided
        if (!empty($data['fields'])) {
            foreach ($data['fields'] as $fieldData) {
                $fieldDef = FieldDefinition::create([
                    'shop_owner_id' => $shop->id,
                    'key' => $fieldData['key'],
                    'label' => $fieldData['label'],
                    'type' => $fieldData['type'],
                    'options' => $fieldData['options'] ?? null,
                    'validation' => $fieldData['validation'] ?? null,
                ]);

                BlueprintField::create([
                    'blueprint_id' => $blueprint->id,
                    'field_definition_id' => $fieldDef->id,
                    'section' => $fieldData['section'] ?? 'details',
                    'sort_order' => $fieldData['sort_order'] ?? 0,
                    'required' => $fieldData['required'] ?? false,
                    'show_in_list' => $fieldData['show_in_list'] ?? false,
                    'show_in_pos' => $fieldData['show_in_pos'] ?? false,
                    'show_on_label' => $fieldData['show_on_label'] ?? false,
                    'is_variant_axis' => $fieldData['is_variant_axis'] ?? false,
                    'is_filterable' => $fieldData['is_filterable'] ?? false,
                    'is_searchable' => $fieldData['is_searchable'] ?? false,
                    'hidden' => $fieldData['hidden'] ?? false,
                ]);
            }
        }

        return response()->json([
            'message' => 'Blueprint created.',
            'blueprint' => $blueprint->load('fields.fieldDefinition'),
        ], 201);
    }

    /**
     * Update a blueprint
     */
    public function update(Request $request, Blueprint $blueprint): JsonResponse
    {
        $this->ownedBlueprint($request, $blueprint);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:draft,active,archived',
            'capabilities' => 'sometimes|array',
            'pricing_policy' => 'sometimes|array',
            'unit_policy' => 'sometimes|array',
        ]);

        $blueprint->update($data);

        return response()->json([
            'message' => 'Blueprint updated.',
            'blueprint' => $blueprint,
        ]);
    }

    /**
     * Delete a blueprint
     */
    public function destroy(Request $request, Blueprint $blueprint): JsonResponse
    {
        $this->ownedBlueprint($request, $blueprint);

        if ($blueprint->is_default) {
            return response()->json([
                'message' => 'Cannot delete the default blueprint.',
            ], 403);
        }

        $blueprint->delete();

        return response()->json([
            'message' => 'Blueprint deleted.',
        ]);
    }

    /**
     * Duplicate a blueprint
     */
    public function duplicate(Request $request, Blueprint $blueprint): JsonResponse
    {
        $this->ownedBlueprint($request, $blueprint);

        $newBlueprint = $blueprint->replicate([
            'is_default',
            'version',
        ]);
        $newBlueprint->name = $blueprint->name . ' (copy)';
        $newBlueprint->is_default = false;
        $newBlueprint->version = 1;
        $newBlueprint->save();

        // Duplicate fields
        foreach ($blueprint->fields as $field) {
            $newField = $field->replicate();
            $newField->blueprint_id = $newBlueprint->id;
            $newField->save();
        }

        return response()->json([
            'message' => 'Blueprint duplicated.',
            'blueprint' => $newBlueprint->load('fields.fieldDefinition'),
        ], 201);
    }

    /**
     * Get blueprint schema for frontend rendering
     */
    public function schema(Request $request, Blueprint $blueprint): JsonResponse
    {
        $this->ownedBlueprint($request, $blueprint);

        $blueprint->load('fields.fieldDefinition');

        return response()->json([
            'blueprint' => $blueprint,
            'schema' => [
                'capabilities' => $blueprint->capabilities,
                'pricing_policy' => $blueprint->pricing_policy,
                'unit_policy' => $blueprint->unit_policy,
                'layout' => $blueprint->layout,
                'fields' => $blueprint->fields->map(function ($field) {
                    return [
                        'key' => $field->fieldDefinition->key,
                        'label' => $field->fieldDefinition->label,
                        'type' => $field->fieldDefinition->type,
                        'options' => $field->fieldDefinition->options,
                        'validation' => $field->fieldDefinition->validation,
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
                }),
            ],
        ]);
    }

    /**
     * Get available presets
     */
    public function presets(Request $request): JsonResponse
    {
        return response()->json([
            'presets' => $this->presetService->getAvailablePresets(),
        ]);
    }

    /**
     * Confirm the blueprint belongs to the caller's shop
     */
    private function ownedBlueprint(Request $request, Blueprint $blueprint): Blueprint
    {
        abort_unless($blueprint->shop_owner_id === $this->shop($request)->id, 404);

        return $blueprint;
    }
}
