<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    use ResolvesShop;

    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $this->authorizeAction($request, 'sales.view');
        $query = $this->visibleReceipts($request, (int) $shop->id)->with('issuedBy:id,name')->withCount('printLogs');
        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());
            $query->where(fn ($q) => $q->where('receipt_number', 'like', "%{$term}%")->orWhere('sale_id', 'like', "%{$term}%"));
        }
        if ($request->filled('from')) $query->whereDate('issued_at', '>=', $request->input('from'));
        if ($request->filled('to')) $query->whereDate('issued_at', '<=', $request->input('to'));
        if ($request->filled('type')) $query->where('receipt_type', $request->string('type')->toString());
        return $query->latest('issued_at')->paginate(min(100, max(1, (int) $request->input('per_page', 25))));
    }

    public function show(Request $request, Receipt $receipt)
    {
        $shop = $this->shop($request);
        $this->authorizeAction($request, 'sales.view');
        return $this->visibleReceipts($request, (int) $shop->id)->whereKey($receipt->id)
            ->with(['issuedBy:id,name', 'sale.receipts'])->withCount('printLogs')->firstOrFail();
    }

    public function printLog(Request $request, Receipt $receipt)
    {
        $shop = $this->shop($request);
        $this->authorizeAction($request, 'sales.view');
        $receipt = $this->visibleReceipts($request, (int) $shop->id)->whereKey($receipt->id)->firstOrFail();
        $data = $request->validate(['action' => 'required|in:print,reprint,pdf']);
        $log = $receipt->printLogs()->create([
            'shop_owner_id' => $shop->id,
            'printed_by' => $request->user()->id,
            'action' => $data['action'],
            'printed_at' => now(),
        ]);
        return response()->json(['logged' => true, 'print_count' => $receipt->printLogs()->count(), 'id' => $log->id], 201);
    }

    private function visibleReceipts(Request $request, int $shopId)
    {
        $query = Receipt::where('shop_owner_id', $shopId);
        if ($request->user()->isBranchHead()) {
            $branchId = $request->user()->staff?->branch_id;
            abort_unless($branchId && DB::table('branches')->where('id', $branchId)->where('shop_owner_id', $shopId)->where('is_active', true)->exists(), 403);
            $query->where('branch_id', $branchId);
        }
        return $query;
    }
}