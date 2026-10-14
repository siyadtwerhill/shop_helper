<?php

namespace App\Http\Controllers;

use App\Models\Blueprint;
use App\Models\FieldDefinition;
use App\Models\BlueprintField;
use App\Services\BlueprintPresetService;
use App\Services\BlueprintSchemaResolver;
use App\Services\ProductSystemFields;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BlueprintController extends Controller
{
    use ResolvesShop;

    private BlueprintPresetService $presetService;
    private BlueprintSchemaResolver $schemaResolver;

    public function __construct(BlueprintPresetService $presetService, BlueprintSchemaResolver $schemaResolver)
    {
        $this->presetService = $presetService;
        $this->schemaResolver = $schemaResolver;
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

        $blueprint->load(['fields.fieldDefinition'])->loadCount('products');

        return response()->json([
            'blueprint' => $blueprint,
        ]);
    }

    private function fieldRules(): array
    {
        return [
            'fields' => 'sometimes|nullable|array|max:60',
            'fields.*.key' => ['required', 'alpha_dash', 'max:60', 'distinct'],
            'fields.*.label' => 'required|string|max:100',
            'fields.*.type' => ['required', Rule::in(['text','textarea','number','decimal','select','multi_select','checkbox','date','boolean','json'])],
            'fields.*.options' => 'nullable|array|max:100',
            'fields.*.validation' => 'nullable|array',
            'fields.*.section' => 'nullable|string|max:50',
            'fields.*.sort_order' => 'nullable|integer|min:0',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.show_in_list' => 'nullable|boolean',
            'fields.*.show_in_pos' => 'nullable|boolean',
            'fields.*.show_on_label' => 'nullable|boolean',
            'fields.*.is_variant_axis' => 'nullable|boolean',
            'fields.*.is_filterable' => 'nullable|boolean',
            'fields.*.is_searchable' => 'nullable|boolean',
            'fields.*.hidden' => 'nullable|boolean',
            'system_fields' => 'sometimes|nullable|array',
            'system_fields.*.hidden' => 'nullable|boolean',
            'system_fields.*.required' => 'nullable|boolean',
        ];
    }

    private function pricingPolicyError(?array $policy): ?JsonResponse
    {
        if (empty($policy)) return null;

        $modes = $policy['allowed_modes'] ?? [];
        $default = $policy['default_mode'] ?? null;
        $margin = $policy['min_margin_percent'] ?? null;

        if ($bad = array_diff($modes, ['fixed', 'negotiable', 'price_range', 'wholesale'])) {
            return response()->json(['message' => 'Invalid pricing mode: ' . implode(', ', $bad)], 422);
        }
        if ($default && $modes && ! in_array($default, $modes)) {
            return response()->json(['message' => 'Default pricing mode must be in allowed modes'], 422);
        }
        if ($margin !== null && ($margin < 0 || $margin > 100)) {
            return response()->json(['message' => 'Minimum margin percent must be between 0 and 100'], 422);
        }
        return null;
    }

    private function variantsBundlesError(?array $caps): ?JsonResponse
    {
        if (! empty($caps['variants']) && ! empty($caps['bundles'])) {
            return response()->json(['message' => 'Variants and bundles cannot both be enabled'], 422);
        }
        return null;
    }

    private function serialDecimalsError(?array $caps): ?JsonResponse
    {
        if (! empty($caps['serial_numbers']) && ! empty($caps['decimal_quantities'])) {
            return response()->json(['message' => 'Serial numbers and decimal quantities cannot both be enabled'], 422);
        }
        return null;
    }

    /**
     * Create a new blueprint
     */
    public function store(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        // Owner-only check
        abort_unless($request->user()->role === 'shop_owner', 403, 'Only shop owners can create blueprints');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'preset_key' => 'nullable|string|in:generic,fashion,grocery,pharmacy,electronics,cafe,blank',
            'capabilities' => 'nullable|array',
            'pricing_policy' => 'nullable|array',
            'unit_policy' => 'nullable|array',
            'layout' => 'nullable|array',
        ] + $this->fieldRules());

        if ($err = $this->pricingPolicyError($data['pricing_policy'] ?? null)) return $err;
        if ($err = $this->variantsBundlesError($data['capabilities'] ?? null)) return $err;
        if ($err = $this->serialDecimalsError($data['capabilities'] ?? null)) return $err;

        $blueprint = DB::transaction(function () use ($data, $shop) {
            $bp = Blueprint::create([
                'shop_owner_id' => $shop->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'preset_key' => $data['preset_key'] ?? null,
                'is_default' => false,
                'status' => 'active',
                'capabilities' => $data['capabilities'] ?? [],
                'pricing_policy' => $data['pricing_policy'] ?? [],
                'unit_policy' => $data['unit_policy'] ?? [],
                'layout' => $data['layout'] ?? [],
                'system_fields' => ProductSystemFields::sanitize($data['system_fields'] ?? null),
                'version' => 1,
            ]);

            if (! empty($data['fields'])) {
                $this->presetService->syncFields($bp, $shop, $data['fields']);
            }
            return $bp;
        });

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
        $shop = $this->shop($request);

        // Owner-only check
        abort_unless($request->user()->role === 'shop_owner', 403, 'Only shop owners can update blueprints');

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:draft,active,archived',
            'capabilities' => 'sometimes|array',
            'pricing_policy' => 'sometimes|array',
            'unit_policy' => 'sometimes|array',
            'layout' => 'sometimes|array',
            'make_default' => 'sometimes|boolean',
        ] + $this->fieldRules());

        if ($err = $this->pricingPolicyError($data['pricing_policy'] ?? null)) return $err;
        if ($err = $this->variantsBundlesError($data['capabilities'] ?? null)) return $err;
        if ($err = $this->serialDecimalsError(array_merge($blueprint->capabilities ?? [], $data['capabilities'] ?? []))) return $err;

        // Prevent archiving the default blueprint
        if (isset($data['status']) && $data['status'] === 'archived' && $blueprint->is_default) {
            return response()->json([
                'message' => 'Cannot archive the default blueprint.',
            ], 403);
        }

        // Make default - unsets previous default in transaction
        if (!empty($data['make_default']) && $data['make_default']) {
            DB::transaction(function () use ($shop, $blueprint) {
                Blueprint::where('shop_owner_id', $shop->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
                $blueprint->update(['is_default' => true]);
            });
            unset($data['make_default']);
        }

        $fields = $data['fields'] ?? null;
        unset($data['fields']);

        $bump = false;

        // Sanitize system_fields and flag for version bump if changed.
        if (array_key_exists('system_fields', $data)) {
            $sanitized = ProductSystemFields::sanitize($data['system_fields']);
            if ($sanitized != ($blueprint->system_fields ?? [])) $bump = true;
            $data['system_fields'] = $sanitized;
        }

        foreach (['capabilities', 'pricing_policy', 'unit_policy'] as $k) {
            if (isset($data[$k]) && $data[$k] != $blueprint->$k) $bump = true;   // loose != on purpose
        }

        DB::transaction(function () use ($blueprint, $data, $fields, $shop, &$bump) {
            $blueprint->update($data);
            if ($fields !== null && $this->presetService->syncFields($blueprint, $shop, $fields)) {
                $bump = true;
            }
        });

        if ($bump) $blueprint->increment('version');

        return response()->json([
            'message' => 'Blueprint updated.',
            'blueprint' => $blueprint->fresh()->load('fields.fieldDefinition'),
        ]);
    }

    /**
     * Delete a blueprint
     */
    public function destroy(Request $request, Blueprint $blueprint): JsonResponse
    {
        $this->ownedBlueprint($request, $blueprint);

        // Owner-only check
        abort_unless($request->user()->role === 'shop_owner', 403, 'Only shop owners can delete blueprints');

        if ($blueprint->is_default) {
            return response()->json([
                'message' => 'Cannot delete the default blueprint.',
            ], 403);
        }

        // Check if products are using this blueprint (including soft-deleted)
        if ($blueprint->products()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'Cannot delete blueprint: it is being used by products. Archive it instead.',
            ], 409);
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

        // Owner-only check
        abort_unless($request->user()->role === 'shop_owner', 403, 'Only shop owners can duplicate blueprints');

        $newBlueprint = DB::transaction(function () use ($blueprint) {
            $newBlueprint = $blueprint->replicate([
                'is_default',
                'version',
            ]);
            $newBlueprint->name = $blueprint->name . ' (copy)';
            $newBlueprint->is_default = false;
            $newBlueprint->status = 'active'; // Always create active copy
            $newBlueprint->version = 1;
            $newBlueprint->save();

            // Duplicate fields (share field definitions)
            foreach ($blueprint->fields as $field) {
                $newField = $field->replicate();
                $newField->blueprint_id = $newBlueprint->id;
                $newField->save();
            }

            return $newBlueprint;
        });

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

        $schema = $this->schemaResolver->resolve($blueprint);

        return response()->json([
            'blueprint' => $blueprint,
            'schema' => $schema,
        ]);
    }

    public function form(Request $request, Blueprint $blueprint): JsonResponse
    {
        $this->ownedBlueprint($request, $blueprint);

        return response()->json($this->schemaResolver->formFor($blueprint));
    }

    /**
     * Get available presets
     */
    public function presets(Request $request): JsonResponse
    {
        return response()->json([
            'presets' => $this->presetService->getAvailablePresets(),
            'system_fields' => array_values(ProductSystemFields::resolve(new Blueprint)),
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
