<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'role' => 'nullable|in:shop_owner,staff,superadmin',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $users = User::with([
            'shopOwner:id,user_id,shop_name',
            'staff:id,user_id,shop_owner_id,status',
            'staff.shopOwner:id,shop_name',
        ])
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['search'] ?? null, function ($query, $term) {
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('username', 'like', "%{$term}%")
                        ->orWhereHas('shopOwner', fn ($shop) => $shop->where('shop_name', 'like', "%{$term}%"))
                        ->orWhereHas('staff.shopOwner', fn ($shop) => $shop->where('shop_name', 'like', "%{$term}%"));
                });
            })
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 20));

        $counts = User::selectRaw('role, COUNT(*) as aggregate')->groupBy('role')->pluck('aggregate', 'role');

        return response()->json([
            'users' => $users,
            'counts' => [
                'all' => (int) $counts->sum(),
                'shop_owner' => (int) ($counts['shop_owner'] ?? 0),
                'staff' => (int) ($counts['staff'] ?? 0),
                'superadmin' => (int) ($counts['superadmin'] ?? 0),
            ],
        ]);
    }

    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    public function updateRole(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|in:shop_owner,superadmin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'message' => 'User role updated successfully',
            'user' => $user
        ]);
    }

    public function getUsersByRole($role)
    {
        if (!in_array($role, ['shop_owner', 'staff', 'superadmin'])) {
            return response()->json(['message' => 'Invalid role'], 400);
        }

        $users = User::where('role', $role)->get();
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:shop_owner,superadmin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
