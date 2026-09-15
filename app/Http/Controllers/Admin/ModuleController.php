<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function index()
    {
        return response()->json([
            'modules' => Module::withCount('features')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:modules',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $module = Module::create($data);

        return response()->json(['module' => $module, 'message' => 'Module created.'], 201);
    }

    public function show(Module $module)
    {
        return response()->json([
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

        return response()->json(['module' => $module, 'message' => 'Module updated.']);
    }

    public function destroy(Module $module)
    {
        $module->delete();

        return response()->json(['message' => 'Module deleted.']);
    }
}
