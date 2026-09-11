<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModuleController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Modules/Index', [
            'modules' => Module::withCount('features')->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Modules/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:modules',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        Module::create($data);
        return back()->with('success', 'Module created.');
    }

    public function show(Module $module)
    {
        return Inertia::render('Admin/Modules/Show', [
            'module' => $module->load('features.permissions'),
        ]);
    }

    public function update(Request $request, Module $module)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => "required|string|max:255|unique:modules,slug,{$module->id}",
            'icon' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);
        $module->update($data);
        return back()->with('success', 'Module updated.');
    }

    public function destroy(Module $module)
    {
        $module->delete();
        return redirect()->route('admin.modules.index');
    }
}