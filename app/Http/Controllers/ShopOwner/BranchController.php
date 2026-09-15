<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Staff;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    private function shop(Request $request)
    {
        $user = $request->user();
        $shop = $user->shopOwner ?? $user->staff?->shopOwner;

        if (!$shop) {
            abort(403, 'Shop not found for this user');
        }

        return $shop;
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);

        $query = Branch::where('shop_owner_id', $shop->id)
            ->withCount('staff')
            ->with('head.user');

        // Branch heads should only see their own branch
        if (auth()->user()->isBranchHead() && auth()->user()->staff?->branch_id) {
            $query->where('id', auth()->user()->staff->branch_id);
        }

        $branches = $query->get();

        return response()->json(['branches' => $branches]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);

        // Branch heads cannot create new branches
        if (auth()->user()->isBranchHead()) {
            return response()->json(['message' => 'Branch heads cannot create new branches.'], 403);
        }

        if ($shop->plan?->max_branches !== null) {
            $current = Branch::where('shop_owner_id', $shop->id)->count();
            if ($current >= $shop->plan->max_branches) {
                return response()->json([
                    'message' => "You've reached your plan's branch limit ({$shop->plan->max_branches}). Upgrade to add more.",
                ], 403);
            }
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'head_staff_id' => 'nullable|exists:staff,id',
        ]);

        $branch = Branch::create([
            'shop_owner_id' => $shop->id,
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
        ]);

        if (!empty($data['head_staff_id'])) {
            $this->assignHead($branch, $data['head_staff_id']);
        }

        return response()->json(['message' => 'Branch created.', 'branch' => $branch->fresh('head.user')], 201);
    }

    public function update(Request $request, Branch $branch)
    {
        $shop = $this->shop($request);
        abort_if($branch->shop_owner_id !== $shop->id, 403);

        // Branch heads can only update their own branch
        if (auth()->user()->isBranchHead()) {
            $branchHeadStaff = auth()->user()->staff;
            abort_if($branch->id !== $branchHeadStaff->branch_id, 403, 'You can only update your own branch');
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'head_staff_id' => 'nullable|exists:staff,id',
            'is_active' => 'sometimes|boolean',
        ]);

        $branch->update($request->only(['name', 'address', 'phone', 'is_active']));

        if ($request->has('head_staff_id')) {
            $this->assignHead($branch, $data['head_staff_id'] ?? null);
        }

        return response()->json(['message' => 'Branch updated.', 'branch' => $branch->fresh('head.user')]);
    }

    public function destroy(Request $request, Branch $branch)
    {
        $shop = $this->shop($request);
        abort_if($branch->shop_owner_id !== $shop->id, 403);

        // Branch heads cannot delete branches
        if (auth()->user()->isBranchHead()) {
            return response()->json(['message' => 'Branch heads cannot delete branches.'], 403);
        }

        if ($branch->staff()->exists()) {
            return response()->json(['message' => 'Move or remove staff from this branch before deleting it.'], 422);
        }

        $branch->delete();

        return response()->json(['message' => 'Branch deleted.']);
    }

    /**
     * Choosing a Branch Head assigns that staff member to this branch
     * (sets their staff.branch_id) — the mockup's tooltip promises this
     * exact behavior, so it's not just a label: appointing someone here
     * also grants them the branch_head role, giving Branch Head real
     * scoped authority rather than being purely cosmetic.
     */
    private function assignHead(Branch $branch, ?int $staffId): void
    {
        // Clear the previous head's role if they're being replaced
        if ($branch->head_staff_id && $branch->head_staff_id !== $staffId) {
            $previousHead = Staff::find($branch->head_staff_id);
            $previousHead?->user?->removeRole('branch_head');
        }

        $branch->update(['head_staff_id' => $staffId]);

        if ($staffId) {
            $staff = Staff::findOrFail($staffId);
            $staff->update(['branch_id' => $branch->id]);
            $staff->user->assignRole('branch_head');
        }
    }
}
