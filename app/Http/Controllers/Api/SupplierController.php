<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Traits\ResolvesShop;
use App\Services\StockLocationService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ResolvesShop;

    public function index(Request $request, StockLocationService $locations)
    {
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        return Supplier::where('shop_owner_id', $shop->id)->orderBy('name')->paginate(50);
    }

    public function store(Request $request, StockLocationService $locations)
    {
        abort_if($request->user()->isBranchHead(), 403, 'Branch Heads cannot manage shop-wide supplier records.');
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50', 'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255', 'tax_id' => 'nullable|string|max:100',
        ]);
        return response()->json(['supplier' => Supplier::create($data + ['shop_owner_id' => $shop->id, 'is_active' => true])], 201);
    }

    public function update(Request $request, Supplier $supplier, StockLocationService $locations)
    {
        abort_if($request->user()->isBranchHead(), 403, 'Branch Heads cannot manage shop-wide supplier records.');
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        abort_unless((int) $supplier->shop_owner_id === (int) $shop->id, 404);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255', 'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50', 'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255', 'tax_id' => 'nullable|string|max:100', 'is_active' => 'sometimes|boolean',
        ]);
        $supplier->update($data);
        return response()->json(['supplier' => $supplier->fresh()]);
    }

    public function destroy(Request $request, Supplier $supplier, StockLocationService $locations)
    {
        abort_if($request->user()->isBranchHead(), 403, 'Branch Heads cannot manage shop-wide supplier records.');
        $shop = $this->shop($request);
        $locations->ensureFeatureEnabled($shop);
        abort_unless((int) $supplier->shop_owner_id === (int) $shop->id, 404);
        if ($supplier->purchases()->exists()) return response()->json(['message' => 'This supplier has purchase history. Deactivate it instead of deleting it.'], 422);
        $supplier->delete();
        return response()->json(['message' => 'Supplier deleted.']);
    }
}
