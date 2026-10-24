<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchHeadAssignmentHistory;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

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
            'head_staff_id' => ['nullable', Rule::exists('staff', 'id')->where('shop_owner_id', $shop->id)],
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
            'head_staff_id' => ['nullable', Rule::exists('staff', 'id')->where('shop_owner_id', $shop->id)],
            'is_active' => 'sometimes|boolean',
        ]);

        $branch->update($request->only(['name', 'address', 'phone', 'is_active']));

        if ($request->has('head_staff_id')) {
            $this->assignHead($branch, $data['head_staff_id']);
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
     * Assign a branch head with transaction safety, additive role assignment, and audit logging.
     * 
     * Transaction-wrapped to ensure branch update, staff assignment, and role changes
     * succeed together or roll back together. Uses assignRole() for additive role assignment
     * (preserves existing roles like Cashier) and removeRole() for clean removal.
     */
    private function assignHead(Branch $branch, ?int $staffId): void
    {
        $shop = $branch->shopOwner;
        $previousHeadStaffId = $branch->head_staff_id;
        $actingUserId = auth()->id();

        DB::transaction(function () use ($branch, $staffId, $shop, $previousHeadStaffId, $actingUserId) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);

            // Step 1: Remove Branch Head role from previous head if being replaced
            if ($previousHeadStaffId && $previousHeadStaffId !== $staffId) {
                $previousHead = Staff::find($previousHeadStaffId);
                if ($previousHead) {
                    // Remove only the Branch Head role, preserve other roles
                    $previousHead->user->removeRole('Branch Head');
                }
            }

            // Step 2: Update branch's head_staff_id
            $branch->update(['head_staff_id' => $staffId]);

            // Step 3: If assigning a new head
            if ($staffId) {
                $staff = Staff::findOrFail($staffId);

                // Validate staff belongs to the same shop
                abort_if($staff->shop_owner_id !== $shop->id, 403, 'Staff must belong to the same shop');
                
                // Validate staff is active
                abort_if($staff->status !== 'active', 422, 'Cannot assign an inactive staff member as branch head');

                // Assign staff to this branch
                $staff->update(['branch_id' => $branch->id]);

                // Assign Branch Head role additively (preserves existing roles)
                $branchHeadRole = Role::where('shop_owner_id', $shop->id)
                    ->where('name', 'Branch Head')
                    ->first();

                if ($branchHeadRole) {
                    $staff->user->assignRole($branchHeadRole);
                }
            }
        });

        // Step 4: Log assignment history after successful transaction
        $action = $staffId ? ($previousHeadStaffId ? 'replaced' : 'assigned') : 'removed';
        BranchHeadAssignmentHistory::log(
            shopOwnerId: $shop->id,
            branchId: $branch->id,
            previousHeadStaffId: $previousHeadStaffId,
            newHeadStaffId: $staffId,
            assignedByUserId: $actingUserId,
            action: $action,
            notes: $staffId ? "Staff ID {$staffId} assigned as branch head" : "Branch head removed"
        );
    }
}
