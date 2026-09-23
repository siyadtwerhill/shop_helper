<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Shop owners have the shop directly; staff / branch heads reach it
     * through their staff record.
     */
    private function shop(Request $request)
    {
        $user = $request->user();
        $shop = $user->shopOwner ?? $user->staff?->shopOwner;

        abort_unless($shop, 403, 'No shop is linked to this account.');

        return $shop;
    }

    /** Route-model binding ignores tenancy, so confirm the category is this shop's. */
    private function owned(Request $request, Category $category): Category
    {
        abort_unless($category->shop_owner_id === $this->shop($request)->id, 404);

        return $category;
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $categories = Category::where('shop_owner_id', $shop->id)
            ->with('parent')
            ->withCount('products')
            ->latest()
            ->get();
        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            // parent must belong to the same shop
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('shop_owner_id', $shop->id),
            ],
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

    public function show(Request $request, Category $category)
    {
        $this->owned($request, $category);

        return response()->json(['category' => $category->load('parent', 'children')]);
    }

    public function update(Request $request, Category $category)
    {
        $shop = $this->shop($request);
        $this->owned($request, $category);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parent_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('shop_owner_id', $shop->id),
                Rule::notIn([$category->id]), // a category can't be its own parent
            ],
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);
        return response()->json(['category' => $category->fresh()]);
    }

    public function destroy(Request $request, Category $category)
    {
        $this->owned($request, $category);

        $category->delete();
        return response()->json(['message' => 'Category deleted.']);
    }
}
