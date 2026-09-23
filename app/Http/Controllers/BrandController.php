<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandController extends Controller
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

    /** Route-model binding ignores tenancy, so confirm the brand is this shop's. */
    private function owned(Request $request, Brand $brand): Brand
    {
        abort_unless($brand->shop_owner_id === $this->shop($request)->id, 404);

        return $brand;
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $brands = Brand::where('shop_owner_id', $shop->id)->withCount('products')->latest()->get();
        return response()->json(['brands' => $brands]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'logo' => 'nullable|image|max:2048',
        ]);

        $slug = Str::slug($data['name']);
        $brand = Brand::create([
            'shop_owner_id' => $shop->id,
            'name' => $data['name'],
            'slug' => $slug,
            'status' => $data['status'] ?? 'active',
            'logo_path' => $request->hasFile('logo') ? $request->file('logo')->store('brands', 'public') : null,
        ]);

        return response()->json(['brand' => $brand], 201);
    }

    public function show(Request $request, Brand $brand)
    {
        $this->owned($request, $brand);

        return response()->json(['brand' => $brand]);
    }

    public function update(Request $request, Brand $brand)
    {
        $this->owned($request, $brand);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:active,inactive',
            'logo' => 'nullable|image|max:2048',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if ($request->hasFile('logo')) {
            if ($brand->logo_path) Storage::disk('public')->delete($brand->logo_path);
            $data['logo_path'] = $request->file('logo')->store('brands', 'public');
        }
        unset($data['logo']);

        $brand->update($data);
        return response()->json(['brand' => $brand->fresh()]);
    }

    public function destroy(Request $request, Brand $brand)
    {
        $this->owned($request, $brand);

        if ($brand->logo_path) Storage::disk('public')->delete($brand->logo_path);
        $brand->delete();
        return response()->json(['message' => 'Brand deleted.']);
    }
}
