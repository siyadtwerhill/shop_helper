<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\ShopOwner\EmployeeController;
use App\Http\Controllers\ShopOwner\RoleController;
use App\Http\Controllers\ShopOwner\BranchController;
use Spatie\Permission\PermissionRegistrar;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();
    
    // Load relationships based on role
    if ($user->role === 'shop_owner') {
        $user->load('shopOwner');
    } elseif ($user->role === 'staff') {
        $user->load('staff.shopOwner');
    }

    // Add permissions to the user response
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

    // Attach role-specific context
    if ($user->role === 'shop_owner') {
        $userData['shop_owner'] = $user->shopOwner;
    } elseif ($user->role === 'staff') {
        $userData['staff'] = $user->staff;
    }

    return $userData;
});

Route::post('/register', [AuthController::class, 'register']);

// One login endpoint for every role — role is read from the user row,
// not guessed by the frontend across three separate endpoints.
Route::post('/login', [AuthController::class, 'login']);

// Only shop owner registration is public - staff are created by shop owners
Route::post('/shop-owner/register', [AuthController::class, 'shopOwnerRegister']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::get('/users/role/{role}', [UserController::class, 'getUsersByRole']);

    // ---- Employee Management (shop_owner / branch_head) ----
    // Temporarily removed permission middleware for frontend testing
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{staff}', [EmployeeController::class, 'update']);
    Route::post('/employees/{staff}/reset-password', [EmployeeController::class, 'resetPassword']);
    Route::delete('/employees/{staff}', [EmployeeController::class, 'destroy']);

    // Temporarily removed permission middleware for frontend testing
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{role}/matrix', [RoleController::class, 'matrix']);
    Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);

    // Temporarily removed permission middleware for frontend testing
    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);
    Route::put('/branches/{branch}', [BranchController::class, 'update']);
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);

    Route::middleware('superadmin')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        Route::get('/admin/modules', [ModuleController::class, 'index']);
        Route::post('/admin/modules', [ModuleController::class, 'store']);
        Route::get('/admin/modules/{module}', [ModuleController::class, 'show']);
        Route::put('/admin/modules/{module}', [ModuleController::class, 'update']);
        Route::delete('/admin/modules/{module}', [ModuleController::class, 'destroy']);

        Route::post('/admin/modules/{module}/features', [FeatureController::class, 'store']);
        Route::put('/admin/features/{feature}', [FeatureController::class, 'update']);
        Route::delete('/admin/features/{feature}', [FeatureController::class, 'destroy']);

        Route::get('/admin/plans', [PlanController::class, 'index']);
        Route::post('/admin/plans', [PlanController::class, 'store']);
        Route::get('/admin/plans/{plan}', [PlanController::class, 'show']);
        Route::put('/admin/plans/{plan}', [PlanController::class, 'update']);
        Route::delete('/admin/plans/{plan}', [PlanController::class, 'destroy']);
    });
});
