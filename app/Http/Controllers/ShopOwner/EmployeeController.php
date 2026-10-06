<?php

namespace App\Http\Controllers\ShopOwner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Staff;
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

    public function index(Request $request)
    {
        $shop = $this->shop($request);
        $this->authorizeAction($request, 'employees.view');

        $query = Staff::with(['user.roles', 'branch'])
            ->where('shop_owner_id', $shop->id);

        $user = $request->user();
        if ($user->isBranchHead() && $user->staff?->branch_id) {
            $query->where('branch_id', $user->staff->branch_id);
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

        return $query->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);
        $user = $request->user();

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
        $this->assertCanGrant($request, $role);

        $branchId = $data['branch_id'] ?? null;
        if ($user->isBranchHead() && $user->staff?->branch_id) {
            $branchId = $user->staff->branch_id;
        }

        $temporary = Str::password(12, symbols: false);

        $staff = DB::transaction(function () use ($data, $shop, $role, $branchId, $temporary) {
            $account = User::create([
                'name' => $data['name'],
                'username' => $this->uniqueUsername($data['name']),
                'email' => $data['email'],
                'password' => Hash::make($temporary),
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
            'temporary_password' => $temporary,
            'staff' => $staff->load('user.roles', 'branch'),
        ], 201);
    }

    public function update(Request $request, Staff $staff)
    {
        $shop = $this->shop($request);
        abort_unless($staff->shop_owner_id === $shop->id, 404);
        $this->authorizeAction($request, 'employees.edit');

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
        if ($request->user()->isBranchHead()) {
            unset($data['branch_id']);
        }

        if (isset($data['name'])) {
            $staff->user->update(['name' => $data['name']]);
        }
        $staff->update(Arr::only($data, ['phone', 'branch_id', 'status']));
        if (($data['status'] ?? null) === 'inactive') {
            $staff->user->tokens()->delete();
        }

        if (isset($data['role_id'])) {
            $role = Role::where('shop_owner_id', $shop->id)->findOrFail($data['role_id']);
            $this->assertCanGrant($request, $role);
            $staff->user->syncRoles([$role]);
        }

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

        abort_if($staff->user_id === $request->user()->id, 422, "You can't remove your own account.");

        $staff->update(['status' => 'inactive']);
        $staff->user->tokens()->delete();

        return response()->json(['message' => 'Employee deactivated. Their sales and history are kept.']);
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
