<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryMovementService;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function __construct(private InventoryMovementService $movements)
    {
    }

    /** Product Details > Activity tab (spec §2, §15). */
    public function index(Product $product)
    {
        return $product->inventoryMovements()
            ->with(['unit.unit', 'creator'])
            ->latest()
            ->paginate(25);
    }

    /** Manual stock correction (e.g. stocktake found a discrepancy). Quantity is signed. */
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'unit_id' => 'required|exists:product_units,id',
            'quantity' => 'required|numeric',
            'note' => 'nullable|string|max:500',
        ]);

        $productUnit = $product->units()->findOrFail($data['unit_id']);

        $movement = $this->movements->adjust(
            $product,
            $productUnit,
            $data['quantity'],
            referenceType: 'manual_adjustment',
            createdBy: $request->user()->id,
            note: $data['note'] ?? null,
        );

        return response()->json($movement->load('unit.unit'), 201);
    }
}