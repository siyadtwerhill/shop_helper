<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchHeadAssignmentHistory;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Models\Sale;
use App\Models\ShopOwner;
use App\Models\Staff;
use App\Services\BranchModeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $branchMode = app(BranchModeService::class)->enabledFor($shop);
        app(BranchModeService::class)->ensureInitialBranch($shop);

        $query = Branch::where('shop_owner_id', $shop->id)
            ->withCount('staff')
            ->with('head.user');

        // Branch heads should only see their own branch
        if (auth()->user()->isBranchHead() && auth()->user()->staff?->branch_id) {
            $query->where('id', auth()->user()->staff->branch_id);
        }

        $branches = $query->get();
        $shop->loadMissing('plan:id,max_branches');

        return response()->json([
            'branches' => $branches,
            'quota' => [
                'used' => Branch::where('shop_owner_id', $shop->id)->count(),
                'limit' => $shop->plan?->max_branches,
            ],
            'branch_mode_enabled' => $branchMode,
        ]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);
        // Branch heads cannot create new branches
        if (auth()->user()->isBranchHead()) {
            return response()->json(['message' => 'Branch heads cannot create new branches.'], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'head_staff_id' => 'prohibited',
        ]);

        $branch = DB::transaction(function () use ($shop, $data) {
            $lockedShop = ShopOwner::whereKey($shop->id)->lockForUpdate()->with('plan')->firstOrFail();
            abort_unless(app(BranchModeService::class)->allowsBranchStorage($lockedShop), 403, 'Your plan does not include branch storage.');
            abort_unless(app(BranchModeService::class)->branchModuleEnabled($lockedShop), 403, 'Your plan does not include the Branches module.');

            $limit = $lockedShop->plan?->max_branches;
            $current = Branch::where('shop_owner_id', $lockedShop->id)->count();
            if ($limit !== null && $current >= $limit) {
                abort(403, "You've reached your plan's branch limit ({$limit}). Upgrade to add more.");
            }

            return Branch::create([
                'shop_owner_id' => $lockedShop->id,
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);
        });

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
            'head_staff_id' => [
                'nullable',
                Rule::exists('staff', 'id')
                    ->where('shop_owner_id', $shop->id)
                    ->where('branch_id', $branch->id),
            ],
            'is_active' => 'sometimes|boolean',
        ]);

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $branch->is_active) {
            abort_if(
                Branch::where('shop_owner_id', $shop->id)->where('is_active', true)->count() <= 1,
                422,
                'Keep at least one active branch for inventory operations.'
            );
            $hasStock = Schema::hasTable('product_stocks')
                && DB::table('product_stocks')->where('branch_id', $branch->id)->where('current_stock', '>', 0)->exists();
            $hasOpenTransfer = Schema::hasTable('stock_transfers') && DB::table('stock_transfers')
                ->whereIn('status', ['draft', 'in_transit'])
                ->where(fn ($query) => $query
                    ->where(fn ($source) => $source->where('source_type', 'branch')->where('source_id', $branch->id))
                    ->orWhere(fn ($destination) => $destination->where('destination_type', 'branch')->where('destination_id', $branch->id)))
                ->exists();
            $hasDraftPurchase = Schema::hasTable('purchases') && DB::table('purchases')
                ->where('location_type', 'branch')->where('location_id', $branch->id)->where('status', 'draft')->exists();
            abort_if($hasStock || $hasOpenTransfer || $hasDraftPurchase, 422, 'Move remaining stock and finish draft purchases or transfers before deactivating this branch.');
        }

        DB::transaction(function () use ($branch, $request, $data) {
            $branch->update($request->only(['name', 'address', 'phone', 'is_active']));

            if ($request->has('head_staff_id')) {
                $this->assignHead($branch, $data['head_staff_id']);
            }
        });

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

        if (Branch::where('shop_owner_id', $shop->id)->where('is_active', true)->count() <= 1) {
            return response()->json(['message' => 'Keep at least one active branch for inventory operations.'], 422);
        }

        if (DB::table('products')->where('branch_id', $branch->id)->exists()
            || (Schema::hasTable('product_stocks') && DB::table('product_stocks')->where('branch_id', $branch->id)->exists())) {
            return response()->json(['message' => 'This branch has product assignments or stock balances. Reassign or reconcile them before deleting it.'], 422);
        }

        if (Sale::where('branch_id', $branch->id)->exists()
            || InventoryMovement::where('branch_id', $branch->id)->exists()) {
            return response()->json(['message' => 'This branch has sales or inventory history and cannot be deleted.'], 422);
        }

        if ((Schema::hasTable('stock_transfers') && DB::table('stock_transfers')->where(function ($query) use ($branch) {
            $query->where(fn ($source) => $source->where('source_type', 'branch')->where('source_id', $branch->id))
                ->orWhere(fn ($destination) => $destination->where('destination_type', 'branch')->where('destination_id', $branch->id));
        })->exists()) || (Schema::hasTable('purchases') && DB::table('purchases')->where('location_type', 'branch')->where('location_id', $branch->id)->exists())) {
            return response()->json(['message' => 'This branch is referenced by purchase or transfer history and cannot be deleted.'], 422);
        }

        $branch->delete();

        return response()->json(['message' => 'Branch deleted.']);
    }

    /** Assign or move a head; branch changes, roles, and audit rows commit together. */
    private function assignHead(Branch $branch, ?int $staffId): void
    {
        $shop = $branch->shopOwner;
        $actingUserId = auth()->id();
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        try {
            DB::transaction(function () use ($branch, $staffId, $shop, $actingUserId, $registrar) {
                $registrar->setPermissionsTeamId($shop->id);
                $lockedBranch = Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $lockedBranch->shop_owner_id === (int) $shop->id, 404);

                $staff = null;
                $branchHeadRoles = Role::where('shop_owner_id', $shop->id)
                    ->whereIn('name', ['Branch Head', 'branch_head'])->get();
                $branchHeadRole = $branchHeadRoles->first();
                $sourceBranches = collect();
                if ($staffId) {
                    $staff = Staff::where('shop_owner_id', $shop->id)
                        ->whereKey($staffId)->lockForUpdate()->firstOrFail();
                    abort_if($staff->status !== 'active', 422, 'Cannot assign an inactive staff member as branch head');
                    abort_unless(
                        (int) $staff->branch_id === (int) $lockedBranch->id,
                        422,
                        'Only an active employee assigned to this branch can become its Branch Head.'
                    );

                    abort_if(! $branchHeadRole, 404, 'Branch Head role is not configured for this shop.');

                    // A head belongs to one branch. Lock and clear any stale/old head pointers.
                    $sourceBranches = Branch::where('shop_owner_id', $shop->id)
                        ->where('head_staff_id', $staff->id)
                        ->where('id', '!=', $lockedBranch->id)
                        ->orderBy('id')->lockForUpdate()->get();
                }

                $previousHeadStaffId = $lockedBranch->head_staff_id;
                foreach ($sourceBranches as $sourceBranch) {
                    $sourceBranch->update(['head_staff_id' => null]);
                    BranchHeadAssignmentHistory::log(
                        shopOwnerId: $shop->id,
                        branchId: $sourceBranch->id,
                        previousHeadStaffId: $staff->id,
                        newHeadStaffId: null,
                        assignedByUserId: $actingUserId,
                        action: 'reassigned',
                        notes: "Staff ID {$staff->id} moved to branch {$lockedBranch->id}"
                    );
                }

                if ((int) $previousHeadStaffId !== (int) $staffId || $previousHeadStaffId === null || $sourceBranches->isNotEmpty()) {
                    $lockedBranch->update(['head_staff_id' => $staffId]);
                }

                if ($staff) {
                    // A Branch Head has exactly one role. Replacing the team role set
                    // avoids combining Branch Head permissions with a previous role.
                    $staff->user->syncRoles([$branchHeadRole]);
                }

                if ($previousHeadStaffId && (int) $previousHeadStaffId !== (int) $staffId) {
                    $previousHead = Staff::where('shop_owner_id', $shop->id)
                        ->whereKey($previousHeadStaffId)->lockForUpdate()->first();

                    if ($previousHead && ! Branch::where('shop_owner_id', $shop->id)
                        ->where('head_staff_id', $previousHead->id)->exists()) {
                        foreach ($branchHeadRoles as $role) {
                            $previousHead->user->removeRole($role);
                        }
                    }
                }

                if ((int) $previousHeadStaffId !== (int) $staffId || $previousHeadStaffId === null) {
                    BranchHeadAssignmentHistory::log(
                        shopOwnerId: $shop->id,
                        branchId: $lockedBranch->id,
                        previousHeadStaffId: $previousHeadStaffId,
                        newHeadStaffId: $staffId,
                        assignedByUserId: $actingUserId,
                        action: $staffId ? ($previousHeadStaffId ? 'replaced' : 'assigned') : 'removed',
                        notes: $staffId ? "Staff ID {$staffId} assigned as branch head" : 'Branch head removed'
                    );
                }
            }, attempts: 3);
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
