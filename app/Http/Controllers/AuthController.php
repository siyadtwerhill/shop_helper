<?php

namespace App\Http\Controllers;

use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Regular user registration removed - only shop owners can register
        return response()->json(['message' => 'Direct user registration is not available. Please contact your shop owner.'], 403);
    }

    /**
     * Single login endpoint for every role (superadmin, shop_owner, staff).
     * Role is read from the user row itself, not guessed by the frontend
     * or split across multiple endpoints — one password check, one place.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $payload = [
            'user' => $user,
            'role' => $user->role,
            'token' => $token,
        ];

        // Attach the role-specific profile so the frontend doesn't need
        // a second request just to get shop/staff context.
        if ($user->role === 'shop_owner') {
            $payload['shop_owner'] = $user->shopOwner;
        } elseif ($user->role === 'staff') {
            $payload['staff'] = $user->staff;
        }

        return response()->json($payload);
    }

    public function shopOwnerRegister(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'owner_name' => 'required|string|max:255',
            'shop_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generate a unique username from the owner's name
        $baseUsername = Str::slug($request->owner_name, '_');
        $username = $baseUsername;
        $suffix = 1;
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . '_' . $suffix++;
        }

        $user = User::create([
            'name' => $request->owner_name,
            'username' => $username,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'shop_owner',
        ]);

        $shopOwner = ShopOwner::create([
            'user_id' => $user->id,
            'shop_name' => $request->shop_name,
            'location' => null,
            'plan_id' => 1, // Default to Free plan
            'staff_count' => 0,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'role' => $user->role,
            'shop_owner' => $shopOwner,
            'token' => $token,
        ], 201);
    }

    public function registerStaff(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shop_owner_id' => 'required|exists:shop_owners,id',
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'position' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => 'staff',
        ]);

        $staff = Staff::create([
            'user_id' => $user->id,
            'shop_owner_id' => $request->shop_owner_id,
            'position' => $request->position,
            'phone' => $request->phone,
        ]);

        $shopOwner = ShopOwner::find($request->shop_owner_id);
        $shopOwner->increment('staff_count');

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'role' => $user->role,
            'staff' => $staff,
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();
        }
        return response()->json(['message' => 'Logged out successfully']);
    }
}
