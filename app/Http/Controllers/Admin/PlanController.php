<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::withCount('modules')->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Plans/Create', [
            'modules' => Module::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans',
            'description' => 'nullable|string',
            'price_monthly' => 'required|integer|min:0',
            'max_staff' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'module_ids' => 'array',
            'module_ids.*' => 'exists:modules,id',
        ]);

        $plan = Plan::create(collect($data)->except('module_ids')->toArray());
        $plan->modules()->sync($data['module_ids'] ?? []);

        return redirect()->route('admin.plans.index')->with('success', 'Plan created.');
    }

    public function edit(Plan $plan)
    {
        return Inertia::render('Admin/Plans/Edit', [
            'plan' => $plan->load('modules:id'),
            'modules' => Module::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => "required|string|max:255|unique:plans,slug,{$plan->id}",
            'description' => 'nullable|string',
            'price_monthly' => 'required|integer|min:0',
            'max_staff' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'module_ids' => 'array',
            'module_ids.*' => 'exists:modules,id',
        ]);

        $plan->update(collect($data)->except('module_ids')->toArray());
        $plan->modules()->sync($data['module_ids'] ?? []);

        return back()->with('success', 'Plan updated.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->shopOwners()->exists()) {
            return back()->with('error', 'Cannot delete a plan with active shops on it.');
        }
        $plan->delete();
        return redirect()->route('admin.plans.index');
    }
}