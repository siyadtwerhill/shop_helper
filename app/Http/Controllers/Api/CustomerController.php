<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /** List all customers */
    public function index(Request $request)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);

        $query = Customer::where('shop_owner_id', $shop->id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate(20);
    }

    /** Show single customer */
    public function show(Request $request, Customer $customer)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);
        abort_unless($customer->shop_owner_id === $shop->id, 403);

        return $customer;
    }

    /** Create customer */
    public function store(Request $request)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $customer = Customer::create([
            'shop_owner_id' => $shop->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'] ?? 'active',
            'total_purchases' => 0,
        ]);

        return response()->json($customer, 201);
    }

    /** Update customer */
    public function update(Request $request, Customer $customer)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);
        abort_unless($customer->shop_owner_id === $shop->id, 403);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
        ]);

        $customer->update($data);

        return response()->json($customer);
    }

    /** Delete customer */
    public function destroy(Request $request, Customer $customer)
    {
        $shop = $request->user()->shopOwner ?? $request->user()->staff?->shopOwner;
        abort_unless($shop, 403);
        abort_unless($customer->shop_owner_id === $shop->id, 403);

        $customer->delete();

        return response()->json(null, 204);
    }
}
