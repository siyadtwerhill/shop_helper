<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Staff;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

        $roles = Role::where('shop_owner_id', $shop->id)
            ->withCount('users')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'employee_count' => $role->users_count,
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
            'role' => ['id' => $role->id, 'name' => $role->name, 'description' => $role->description],
            'available_modules' => $availableModules,
            'locked_modules' => $lockedModules,
            'granted_permission_ids' => $rolePermissionIds,
        ]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

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

        $role->update($data);

        return response()->json(['message' => 'Role updated.', 'role' => $role]);
    }

    public function destroy(Request $request, Role $role)
    {
        $shop = $this->shop($request);
        abort_if($role->shop_owner_id !== $shop->id, 403);

        if (Staff::whereHas('user.roles', fn ($q) => $q->where('roles.id', $role->id))->exists()) {
            return response()->json(['message' => 'Reassign employees off this role before deleting it.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }
}
