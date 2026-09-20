<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    private function shop(Request $request)
    {
        return $request->user()->shopOwner;
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $brands = Brand::where('shop_owner_id', $shop->id)->latest()->get();
        return response()->json(['brands' => $brands]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $slug = Str::slug($data['name']);
        $brand = Brand::create([
            'shop_owner_id' => $shop->id,
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        return response()->json(['brand' => $brand], 201);
    }

    public function show(Brand $brand)
    {
        return response()->json(['brand' => $brand]);
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $brand->update($data);
        return response()->json(['brand' => $brand->fresh()]);
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();
        return response()->json(['message' => 'Brand deleted.']);
    }
}