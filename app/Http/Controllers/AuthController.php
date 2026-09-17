<?php

namespace App\Http\Controllers;

use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

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

        $user = User::where('email', $request->email)->with('shopOwner')->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        // Build user data with permissions
        $userData = $user->toArray();
        if ($user->role === 'staff') {
            if ($shopId = $user->staff?->shop_owner_id) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($shopId);
            }
            $userData['permissions'] = $user->getAllPermissions()->pluck('name')->toArray();
        } else {
            // Shop owners and superadmins have all permissions
            $userData['permissions'] = [];
        }

        $payload = [
            'user' => $userData,
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

        // Default to Free plan (plan_id = 1)
        // The plan determines which modules and features are available
        $shopOwner = ShopOwner::create([
            'user_id' => $user->id,
            'shop_name' => $request->shop_name,
            'location' => null,
            'plan_id' => 1, // Default to Free plan
            'staff_count' => 0,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        $userData = $user->toArray();
        $userData['permissions'] = []; // Shop owners have all permissions

        return response()->json([
            'user' => $userData,
            'role' => $user->role,
            'shop_owner' => $shopOwner,
            'token' => $token,
        ], 201);
    }

    // Staff registration removed - employees are only created by shop owners through the admin interface

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();
        }
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            // Shop owner specific fields
            'shop_name' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'shop_phone' => 'nullable|string|max:20',
            'business_type' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->name = $request->name;
        $user->email = $request->email;
        
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }

        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $path = $file->store('profile-photos', 'public');
            $user->profile_photo = '/storage/' . $path;
        }

        $user->save();

        // Update shop owner fields if user is a shop owner
        if ($user->role === 'shop_owner' && $user->shopOwner) {
            $shopOwner = $user->shopOwner;
            
            if ($request->has('shop_name')) {
                $shopOwner->shop_name = $request->shop_name;
            }
            if ($request->has('location')) {
                $shopOwner->location = $request->location;
            }
            if ($request->has('address')) {
                $shopOwner->address = $request->address;
            }
            if ($request->has('shop_phone')) {
                $shopOwner->phone = $request->shop_phone;
            }
            if ($request->has('business_type')) {
                $shopOwner->business_type = $request->business_type;
            }
            if ($request->has('tax_id')) {
                $shopOwner->tax_id = $request->tax_id;
            }
            if ($request->has('description')) {
                $shopOwner->description = $request->description;
            }
            if ($request->has('website')) {
                $shopOwner->website = $request->website;
            }
            
            $shopOwner->save();
        }

        // Load relationships based on role
        if ($user->role === 'shop_owner') {
            $user->load('shopOwner');
        } elseif ($user->role === 'staff') {
            $user->load('staff.shopOwner');
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->toArray(),
        ]);
    }
}
