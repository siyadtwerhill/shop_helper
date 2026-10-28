<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Staff;
use App\Models\ShopOwner;
use App\Models\User;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class EmployeeController extends Controller
{
    use ResolvesShop;

    private const DEFAULT_EMPLOYEE_PASSWORD = '12345678';

    public function index(Request $request)
    {
        $shop = $this->shop($request);
        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        $this->authorizeAction($request, 'employees.view');
        $isBranchHead = $this->branchHeadFor($request, $shop);

        $query = Staff::with(['user.roles', 'branch'])
            ->where('shop_owner_id', $shop->id);

        $user = $request->user();
        if ($isBranchHead) {
            $query->where('branch_id', $user->staff->branch_id);
        }

        $filters = $request->validate([
            'branch_id' => 'sometimes|integer',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
        if (isset($filters['branch_id'])) {
            abort_unless(
                Branch::where('shop_owner_id', $shop->id)->whereKey($filters['branch_id'])->exists(),
                404
            );
            if ($isBranchHead) {
                abort_unless((int) $filters['branch_id'] === (int) $user->staff->branch_id, 404);
            }
            $query->where('branch_id', $filters['branch_id']);
        }

        if ($request->filled('role')) {
            $query->whereHas('user.roles', fn ($q) => $q->where('name', $request->role));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $term = addcslashes($request->search, '%_');
            $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', '%' . $term . '%')
                ->orWhere('email', 'like', '%' . $term . '%'));
        }

        $shop->loadMissing('plan:id,max_staff');

        return response()->json([
            'employees' => $query->latest()->paginate($filters['per_page'] ?? 20),
            'quota' => $isBranchHead ? null : [
                'used' => Staff::where('shop_owner_id', $shop->id)->count(),
                'limit' => $shop->plan?->max_staff,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);
        $user = $request->user();
        $isBranchHead = $this->branchHeadFor($request, $shop);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('shop_owner_id', $shop->id)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('shop_owner_id', $shop->id)],
        ]);

        if ($shop->plan?->max_staff !== null
            && Staff::where('shop_owner_id', $shop->id)->count() >= $shop->plan->max_staff) {
            return response()->json(['message' => "You've reached your plan's staff limit ({$shop->plan->max_staff}). Upgrade to add more."], 403);
        }

        $role = Role::where('shop_owner_id', $shop->id)->findOrFail($data['role_id']);
        abort_if($this->isBranchHeadRole($role), 403, 'Assign Branch Heads from the branch assignment form.');
        $this->assertCanGrant($request, $role);

        $branchId = $data['branch_id'] ?? null;
        if ($isBranchHead) {
            $branchId = $user->staff->branch_id;
        }

        $defaultPassword = self::DEFAULT_EMPLOYEE_PASSWORD;

        $staff = DB::transaction(function () use ($data, $shop, $role, $branchId, $defaultPassword) {
            $account = User::create([
                'name' => $data['name'],
                'username' => $this->uniqueUsername($data['name']),
                'email' => $data['email'],
                'password' => Hash::make($defaultPassword),
                'role' => 'staff',
            ]);
            $staff = Staff::create([
                'user_id' => $account->id,
                'shop_owner_id' => $shop->id,
                'branch_id' => $branchId,
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
            ]);
            app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
            $account->assignRole($role);

            return $staff;
        });

        return response()->json([
            'message' => 'Employee added.',
            'default_password' => $defaultPassword,
            'staff' => $staff->load('user.roles', 'branch'),
        ], 201);
    }

    public function update(Request $request, Staff $staff)
    {
        $shop = $this->shop($request);
        abort_unless($staff->shop_owner_id === $shop->id, 404);
        $this->authorizeAction($request, 'employees.edit');
        $isBranchHead = $this->branchHeadFor($request, $shop);
        if ($isBranchHead) {
            abort_unless((int) $staff->branch_id === (int) $request->user()->staff->branch_id, 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:30',
            'role_id' => ['sometimes', 'integer', Rule::exists('roles', 'id')->where('shop_owner_id', $shop->id)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('shop_owner_id', $shop->id)],
            'status' => 'sometimes|in:active,inactive',
        ]);

        abort_if(
            $staff->user_id === $request->user()->id && (isset($data['role_id']) || isset($data['status'])),
            403,
            "You can't change your own role or status."
        );
        if ($isBranchHead) {
            unset($data['branch_id']);
        }

        DB::transaction(function () use ($data, $staff, $shop, $request, $isBranchHead) {
            $lockedStaff = Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
            $lockedStaff->load('user');
            $lockedStaff->user->unsetRelation('roles')->unsetRelation('permissions');
            $hasBranchHeadRole = $lockedStaff->user->hasAnyRole(['Branch Head', 'branch_head']);
            $isAssignedBranchHead = Branch::where('shop_owner_id', $shop->id)
                ->where('head_staff_id', $lockedStaff->id)
                ->exists();

            if (($hasBranchHeadRole || $isAssignedBranchHead)
                && (array_key_exists('role_id', $data)
                    || array_key_exists('branch_id', $data)
                    || ($data['status'] ?? null) === 'inactive')) {
                abort(403, 'Unassign the Branch Head from the branch form before changing their role, branch, or status.');
            }

            if (isset($data['name'])) {
                $lockedStaff->user->update(['name' => $data['name']]);
            }
            $lockedStaff->update(Arr::only($data, ['phone', 'branch_id', 'status']));
            if (($data['status'] ?? null) === 'inactive') {
                $lockedStaff->user->tokens()->delete();
            }

            if (isset($data['role_id'])) {
                $role = Role::where('shop_owner_id', $shop->id)->findOrFail($data['role_id']);
                abort_if($this->isBranchHeadRole($role), 403, 'Assign Branch Heads from the branch assignment form.');
                $this->assertCanGrant($request, $role);
                $lockedStaff->user->unsetRelation('roles')->syncRoles([$role]);
            }
        });

        return response()->json([
            'message' => 'Employee updated.',
            'staff' => $staff->fresh()->load('user.roles', 'branch'),
        ]);
    }

    public function destroy(Request $request, Staff $staff)
    {
        $shop = $this->shop($request);
        abort_unless($staff->shop_owner_id === $shop->id, 404);
        $this->authorizeAction($request, 'employees.delete');
        $isBranchHead = $this->branchHeadFor($request, $shop);
        if ($isBranchHead) {
            abort_unless((int) $staff->branch_id === (int) $request->user()->staff->branch_id, 404);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        $staff->load('user');
        $staff->user->unsetRelation('roles')->unsetRelation('permissions');
        abort_if(
            $staff->user->hasAnyRole(['Branch Head', 'branch_head'])
                || Branch::where('shop_owner_id', $shop->id)->where('head_staff_id', $staff->id)->exists(),
            403,
            'Unassign the Branch Head from the branch form before deactivating them.'
        );

        abort_if($staff->user_id === $request->user()->id, 422, "You can't remove your own account.");

        $staff->update(['status' => 'inactive']);
        $staff->user->tokens()->delete();

        return response()->json(['message' => 'Employee deactivated. Their sales and history are kept.']);
    }

    private function branchHeadFor(Request $request, ShopOwner $shop): bool
    {
        $user = $request->user();
        if ($user->isShopOwner() || $user->isSuperAdmin()) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $hasBranchHeadRole = $user->hasAnyRole(['Branch Head', 'branch_head']);

        if ($hasBranchHeadRole) {
            abort_unless($user->isBranchHead(), 403, 'A valid branch-head assignment is required.');
        }

        return $hasBranchHeadRole;
    }

    private function isBranchHeadRole(Role $role): bool
    {
        return in_array(strtolower($role->name), ['branch head', 'branch_head'], true);
    }

    private function uniqueUsername(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'user';
        $username = $base;
        for ($n = 1; User::where('username', $username)->exists(); $n++) {
            $username = $base . '_' . $n;
        }
        return $username;
    }
}
