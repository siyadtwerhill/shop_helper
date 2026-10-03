<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\InventoryMovementService;
use App\Services\ProductActivityLogger;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    use ResolvesShop;

    public function __construct(
        private InventoryMovementService $movements,
        private ProductActivityLogger $activity,
    ) {}

    /** Product Details > Activity tab. */
    public function index(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);

        return $product->inventoryMovements()
            ->with(['unit.unit', 'creator:id,name'])
            ->when($request->filled('variant_id'), fn ($q) => $q->where('variant_id', $request->variant_id))
            ->latest('id')
            ->paginate(25);
    }

    /** Manual stock correction (e.g. stocktake found a discrepancy). Quantity is signed. */
    public function store(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);
        $this->authorizeAction($request, 'products.edit');

        abort_if($product->stock_mode === 'from_components', 422, 'Bundle stock is calculated from its components.');

        $data = $request->validate([
            'unit_id' => 'required|integer',
            'variant_id' => 'nullable|integer',
            'quantity' => ['required', 'regex:/^-?\d{1,11}(\.\d{1,4})?$/'],
            'note' => 'nullable|string|max:500',
        ]);

        abort_if(bccomp($data['quantity'], '0', 4) === 0, 422, 'Quantity cannot be zero.');

        $decimals = (bool) (($product->blueprint?->capabilities ?? [])['decimal_quantities'] ?? true);
        abort_if(
            ! $decimals && bccomp(bcmul($data['quantity'], '1', 0), $data['quantity'], 4) !== 0,
            422, 'This product type only allows whole quantities.'
        );

        $unit = $product->units()->findOrFail($data['unit_id']);
        $variant = ! empty($data['variant_id']) ? $product->variants()->findOrFail($data['variant_id']) : null;

        $movement = $this->movements->adjust(
            $product, $unit, $data['quantity'],
            referenceType: 'manual_adjustment',
            createdBy: $request->user()->id,
            note: $data['note'] ?? null,
            variant: $variant,
        );

        $this->activity->log($product, 'stock_adjusted', $request->user()->id, description: "Stock adjusted by {$data['quantity']}");

        return response()->json($movement->load('unit.unit'), 201);
    }
}
