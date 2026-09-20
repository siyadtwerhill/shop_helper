<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    private function shop(Request $request)
    {
        return $request->user()->shopOwner;
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $categories = Category::where('shop_owner_id', $shop->id)
            ->with('parent')
            ->latest()
            ->get();
        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $slug = Str::slug($data['name']);
        $category = Category::create([
            'shop_owner_id' => $shop->id,
            'name' => $data['name'],
            'slug' => $slug,
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        return response()->json(['category' => $category], 201);
    }

    public function show(Category $category)
    {
        return response()->json(['category' => $category->load('parent', 'children')]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);
        return response()->json(['category' => $category->fresh()]);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['message' => 'Category deleted.']);
    }
}