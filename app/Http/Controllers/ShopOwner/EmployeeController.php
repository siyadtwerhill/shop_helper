<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmployeeInvitation;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EmployeeController extends Controller
{
    /**
     * Every method below assumes the authenticated user is a shop_owner
     * or a branch_head — set the Spatie team context to their shop before
     * any role/permission check runs.
     */
    private function shop(Request $request)
    {
        $user = $request->user();
        $shop = $user->shopOwner ?? $user->staff?->shopOwner;

        if (!$shop) {
            abort(403, 'Shop not found for this user');
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        return $shop;
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);

        $query = Staff::with(['user', 'branch'])
            ->where('shop_owner_id', $shop->id);

        // Apply branch scope automatically via BelongsToBranch trait
        // Branch heads will only see their own branch's staff

        if ($request->filled('role')) {
            $query->whereHas('user.roles', fn ($q) => $q->where('name', $request->role));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', '%' . addcslashes($term, '%_') . '%')
                ->orWhere('email', 'like', '%' . addcslashes($term, '%_') . '%'));
        }

        $employees = $query->latest()->paginate(20);

        // Count should respect branch scope too
        $totalCountQuery = Staff::where('shop_owner_id', $shop->id);
        if (auth()->user()->isBranchHead() && auth()->user()->staff?->branch_id) {
            $totalCountQuery->where('branch_id', auth()->user()->staff->branch_id);
        }

        return response()->json([
            'employees' => $employees,
            'total_count' => $totalCountQuery->count(),
        ]);
    }

    /**
     * Add Employee drawer submit. Either invites by email (creates an
     * EmployeeInvitation, no login exists yet) or creates the account
     * directly with a temp password, depending on the toggle.
     */
    public function store(Request $request)
    {
        $shop = $this->shop($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:255',
            'role' => 'required|string|exists:roles,name',
            'branch_id' => 'nullable|exists:branches,id',
            'send_invite' => 'boolean',
            'temp_password' => 'required_if:send_invite,false|nullable|string|min:8',
        ]);

        // Enforce the shop's plan max_staff limit
        if ($shop->plan?->max_staff !== null) {
            $current = Staff::where('shop_owner_id', $shop->id)->count();
            if ($current >= $shop->plan->max_staff) {
                return response()->json([
                    'message' => "You've reached your plan's staff limit ({$shop->plan->max_staff}). Upgrade to add more.",
                ], 403);
            }
        }

        $role = Role::where('name', $data['role'])->where('shop_owner_id', $shop->id)->firstOrFail();

        if ($data['send_invite'] ?? true) {
            $invitation = EmployeeInvitation::createFor($shop, [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'role_id' => $role->id,
                'branch_id' => $data['branch_id'] ?? null,
            ]);

            // Mail::to($invitation->email)->send(new EmployeeInviteMail($invitation));

            return response()->json(['message' => 'Invite sent.', 'invitation' => $invitation], 201);
        }

        // Direct creation with a temporary password
        $username = $this->uniqueUsername($data['name']);

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $data['email'],
            'password' => Hash::make($data['temp_password']),
            'role' => 'staff',
        ]);

        $staff = Staff::create([
            'user_id' => $user->id,
            'shop_owner_id' => $shop->id,
            'branch_id' => $data['branch_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return response()->json(['message' => 'Employee added.', 'staff' => $staff->load('user', 'branch')], 201);
    }

    public function update(Request $request, Staff $staff)
    {
        $shop = $this->shop($request);
        abort_if($staff->shop_owner_id !== $shop->id, 403);

        // Additional permission check: branch heads can only edit staff in their branch
        if (auth()->user()->isBranchHead()) {
            $branchHeadStaff = auth()->user()->staff;
            abort_if($staff->branch_id !== $branchHeadStaff->branch_id, 403, 'You can only edit staff in your branch');
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:255',
            'role' => 'sometimes|string|exists:roles,name',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if (isset($data['name'])) {
            $staff->user->update(['name' => $data['name']]);
        }

        $staff->update($request->only(['phone', 'branch_id', 'status']));

        if (isset($data['role'])) {
            $role = Role::where('name', $data['role'])->where('shop_owner_id', $shop->id)->firstOrFail();
            $staff->user->syncRoles([$role]);
        }

        return response()->json(['message' => 'Employee updated.', 'staff' => $staff->fresh(['user', 'branch'])]);
    }

    public function resetPassword(Request $request, Staff $staff)
    {
        $shop = $this->shop($request);
        abort_if($staff->shop_owner_id !== $shop->id, 403);

        // Branch heads can only reset passwords for staff in their branch
        if (auth()->user()->isBranchHead()) {
            $branchHeadStaff = auth()->user()->staff;
            abort_if($staff->branch_id !== $branchHeadStaff->branch_id, 403, 'You can only reset passwords for staff in your branch');
        }

        $status = Password::sendResetLink(['email' => $staff->user->email]);

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Password reset email sent.'])
            : response()->json(['message' => 'Could not send reset email.'], 500);
    }

    public function destroy(Request $request, Staff $staff)
    {
        $shop = $this->shop($request);
        abort_if($staff->shop_owner_id !== $shop->id, 403);

        // Branch heads can only delete staff in their branch
        if (auth()->user()->isBranchHead()) {
            $branchHeadStaff = auth()->user()->staff;
            abort_if($staff->branch_id !== $branchHeadStaff->branch_id, 403, 'You can only delete staff in your branch');
        }

        $staff->user->delete(); // cascades to staff via FK, or handle manually if no cascade

        return response()->json(['message' => 'Employee deleted permanently.']);
    }

    private function uniqueUsername(string $name): string
    {
        $base = Str::slug($name, '_');
        $username = $base;
        $suffix = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base . '_' . $suffix++;
        }
        return $username;
    }
}
