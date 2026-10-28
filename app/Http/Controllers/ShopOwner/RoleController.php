<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private function shop(Request $request)
    {
        $user = $request->user();
        $shop = $user->shopOwner ?? $user->staff?->shopOwner;

        if (!$shop) {
            abort(403, 'Shop not found for this user');
        }

        // Only set team context if teams are enabled
        if (config('permission.teams')) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        }
        return $shop;
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);

        $roleModels = Role::where('shop_owner_id', $shop->id)->get();

        $employeeCounts = Staff::query()
            ->join('model_has_roles', 'staff.user_id', '=', 'model_has_roles.model_id')
            ->where('staff.shop_owner_id', $shop->id)
            ->where('model_has_roles.model_type', User::class)
            ->whereIn('model_has_roles.role_id', $roleModels->pluck('id'))
            ->groupBy('model_has_roles.role_id')
            ->pluck(DB::raw('count(*)'), 'model_has_roles.role_id');

        $roles = $roleModels->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'is_system' => $this->isSystemRole($role),
            'employee_count' => (int) ($employeeCounts[$role->id] ?? 0),
        ]);

        return response()->json(['roles' => $roles]);
    }

    /**
     * The permission matrix data: every module the shop's plan includes,
     * with its features and each feature's permission ids — plus which
     * of those the given role currently has. Matches the mockup's
     * Sales/Products/Employees blocks with locked "Pro plan" modules
     * shown separately.
     */
    public function matrix(Request $request, Role $role)
    {
        $shop = $this->shop($request);
        abort_if($role->shop_owner_id !== $shop->id, 403);

        $includedModuleIds = $shop->plan?->modules()->pluck('modules.id') ?? collect();

        $availableModules = Module::whereIn('id', $includedModuleIds)
            ->with('features.permissions')
            ->orderBy('sort_order')
            ->get();

        $lockedModules = Module::whereNotIn('id', $includedModuleIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $rolePermissionIds = $role->permissions()->pluck('id')->toArray();

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $this->isSystemRole($role),
            ],
            'available_modules' => $availableModules,
            'locked_modules' => $lockedModules,
            'granted_permission_ids' => $rolePermissionIds,
        ]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where(fn ($query) => $query->where('shop_owner_id', $shop->id)),
            ],
            'description' => 'nullable|string|max:255',
        ]);

        if ($this->isBranchHeadRoleName($data['name'])) {
            return response()->json(['message' => 'Branch Head is a system role. Assign it from a branch instead.'], 422);
        }

        // Spatie Teams scopes uniqueness per team automatically once
        // config('permission.teams') is true and the team id is set above.
        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'guard_name' => 'web',
            'shop_owner_id' => $shop->id,
        ]);

        return response()->json(['message' => 'Role created.', 'role' => $role], 201);
    }

    /**
     * Save the permission matrix. Body: { permission_ids: [1, 5, 9, ...] }
     * — the full desired set, computed client-side from the checkbox grid
     * (Full Control just means all 4 action-permission ids for that row
     * are included; there's no separate "full_control" permission row).
     */
    public function updatePermissions(Request $request, Role $role)
    {
        $shop = $this->shop($request);
        abort_if($role->shop_owner_id !== $shop->id, 403);

        $data = $request->validate([
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        // Guard against granting permissions from modules outside the
        // shop's plan, even if the frontend sends them by mistake.
        $includedModuleIds = $shop->plan?->modules()->pluck('modules.id') ?? collect();
        $allowedPermissionIds = Permission::whereHas(
            'feature',
            fn ($q) => $q->whereIn('module_id', $includedModuleIds)
        )->pluck('id');

        $safeIds = collect($data['permission_ids'] ?? [])->intersect($allowedPermissionIds)->values();

        $role->syncPermissions($safeIds->all());

        return response()->json(['message' => 'Permissions updated.']);
    }

    public function update(Request $request, Role $role)
    {
        $shop = $this->shop($request);
        abort_if($role->shop_owner_id !== $shop->id, 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        if ($this->isSystemRole($role)) {
            return response()->json(['message' => 'System roles can only be changed through their permissions.'], 422);
        }
        if (isset($data['name']) && $this->isBranchHeadRoleName($data['name'])) {
            return response()->json(['message' => 'Branch Head is a system role and cannot be renamed or assigned through employee roles.'], 422);
        }

        $role->update($data);

        return response()->json(['message' => 'Role updated.', 'role' => $role]);
    }

    public function destroy(Request $request, Role $role)
    {
        $shop = $this->shop($request);
        abort_if($role->shop_owner_id !== $shop->id, 403);

        // Prevent deletion of system roles
        if ($this->isSystemRole($role)) {
            return response()->json(['message' => 'Cannot delete system roles.'], 422);
        }

        $hasAssignedEmployees = Staff::query()
            ->join('model_has_roles', 'staff.user_id', '=', 'model_has_roles.model_id')
            ->where('staff.shop_owner_id', $shop->id)
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.role_id', $role->id)
            ->exists();

        if ($hasAssignedEmployees) {
            return response()->json(['message' => 'Reassign employees off this role before deleting it.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    private function isSystemRole(Role $role): bool
    {
        return (bool) $role->is_system || $this->isBranchHeadRoleName($role->name);
    }

    private function isBranchHeadRoleName(string $name): bool
    {
        return str_replace('_', ' ', strtolower(trim($name))) === 'branch head';
    }
}
