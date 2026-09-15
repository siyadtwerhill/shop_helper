<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Module;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function store(Request $request, Module $module)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:features',
        ]);

        $feature = $module->features()->create($data);

        return response()->json([
            'feature' => $feature,
            'message' => 'Feature added — 4 permissions created automatically.',
        ], 201);
    }

    public function update(Request $request, Feature $feature)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sort_order' => 'integer',
        ]);

        $feature->update($data);

        return response()->json(['feature' => $feature, 'message' => 'Feature updated.']);
    }

    public function destroy(Feature $feature)
    {
        $feature->delete();

        return response()->json(['message' => 'Feature and its permissions removed.']);
    }
}
