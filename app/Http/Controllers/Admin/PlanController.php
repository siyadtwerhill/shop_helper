<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return response()->json([
            'plans' => Plan::withCount('modules')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $plan = Plan::create(collect($data)->except('module_ids')->toArray());
        $plan->modules()->sync($data['module_ids'] ?? []);

        return response()->json(['plan' => $plan->load('modules:id'), 'message' => 'Plan created.'], 201);
    }

    public function show(Plan $plan)
    {
        return response()->json([
            'plan' => $plan->load('modules:id'),
            'modules' => Module::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $this->validateData($request, $plan->id);

        $plan->update(collect($data)->except('module_ids')->toArray());
        $plan->modules()->sync($data['module_ids'] ?? []);

        return response()->json(['plan' => $plan->load('modules:id'), 'message' => 'Plan updated.']);
    }

    public function destroy(Plan $plan)
    {
        if ($plan->shopOwners()->exists()) {
            return response()->json(['message' => 'Cannot delete a plan with active shops on it.'], 422);
        }

        $plan->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }

    private function validateData(Request $request, ?int $planId = null): array
    {
        $slugRule = $planId
            ? "required|string|max:255|unique:plans,slug,{$planId}"
            : 'required|string|max:255|unique:plans';

        return $request->validate([
            'name' => 'required|string|max:255',
            'slug' => $slugRule,
            'description' => 'nullable|string',
            'price_monthly' => 'required|integer|min:0',
            'max_staff' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'module_ids' => 'array',
            'module_ids.*' => 'exists:modules,id',
        ]);
    }
}
