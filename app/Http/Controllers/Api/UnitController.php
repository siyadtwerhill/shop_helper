<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $shopOwnerId = $request->user()->shopOwner->id;

        return Unit::availableTo($shopOwnerId)
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:20',
            'type' => 'required|in:weight,volume,length,quantity,packaging',
        ]);

        $data['shop_owner_id'] = $request->user()->shopOwner->id;
        $data['is_system'] = false;

        $unit = Unit::create($data);

        return response()->json($unit, 201);
    }

    public function update(Request $request, Unit $unit)
    {
        abort_if($unit->is_system, 403, 'System units cannot be edited.');
        abort_if($unit->shop_owner_id !== $request->user()->shopOwner->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'symbol' => 'sometimes|string|max:20',
            'type' => 'sometimes|in:weight,volume,length,quantity,packaging',
        ]);

        $unit->update($data);

        return response()->json($unit);
    }

    public function destroy(Request $request, Unit $unit)
    {
        abort_if($unit->is_system, 403, 'System units cannot be deleted.');
        abort_if($unit->shop_owner_id !== $request->user()->shopOwner->id, 403);
        abort_if($unit->productUnits()->exists(), 409, 'Unit is still in use on a product.');

        $unit->delete();

        return response()->json(null, 204);
    }
}