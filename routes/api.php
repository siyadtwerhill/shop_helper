<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\ProductScannerController;
use App\Http\Controllers\Api\SaleItemController;
use App\Http\Controllers\Api\PriceQuoteController;
use App\Http\Controllers\Api\ProductPriceRuleController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\ProductBundleController;
use App\Http\Controllers\Api\BundleSaleItemController;
use App\Http\Controllers\Api\ProductActivityLogController;
use App\Http\Controllers\Api\ProductLabelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\ShopOwner\EmployeeController;
use App\Http\Controllers\ShopOwner\RoleController;
use App\Http\Controllers\ShopOwner\BranchController;
use Spatie\Permission\PermissionRegistrar;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    $user = $request->user();

    // Load relationships based on role.
    // .plan is nested on so the frontend can show the shop's plan name
    // (e.g. on the profile page) without a second request.
    if ($user->role === 'shop_owner') {
        $user->load(['shopOwner.plan', 'shopOwner.subscription.plan']);
    } elseif ($user->role === 'staff') {
        $user->load(['staff.shopOwner.plan', 'staff.shopOwner.subscription.plan']);
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

    // Shop-owner subscription management
    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::get('/subscription/payments', [SubscriptionController::class, 'history']);
    Route::post('/subscription/initiate', [SubscriptionController::class, 'initiate']);
    Route::post('/subscription/payments/{payment}/proof', [SubscriptionController::class, 'uploadProof']);
    Route::post('/subscription/downgrade', [SubscriptionController::class, 'scheduleDowngrade']);

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

    // ---- Products, Categories, Brands ----
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::post('/products/lookup-barcode', [ProductController::class, 'lookupByBarcode']);

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('brands', BrandController::class);

    // ---- Units & Product Units ----
    Route::apiResource('units', UnitController::class)->except(['show']);

    Route::get('products/{product}/units', [ProductUnitController::class, 'index']);
    Route::post('products/{product}/units', [ProductUnitController::class, 'store']);
    Route::put('products/{product}/units/{productUnit}', [ProductUnitController::class, 'update']);
    Route::delete('products/{product}/units/{productUnit}', [ProductUnitController::class, 'destroy']);

    // ---- Inventory Movements & Scanner ----
    Route::post('scanner/lookup', [ProductScannerController::class, 'lookup']);

    Route::get('products/{product}/movements', [InventoryMovementController::class, 'index']);
    Route::post('products/{product}/movements', [InventoryMovementController::class, 'store']);

    // ---- POS Sale Items ----
    Route::post('pos/sale-items', [SaleItemController::class, 'store']);

    // ---- Pricing ----
    Route::post('pos/price-quote', [PriceQuoteController::class, 'store']);

    Route::get('products/{product}/price-rules', [ProductPriceRuleController::class, 'index']);
    Route::post('products/{product}/price-rules', [ProductPriceRuleController::class, 'store']);
    Route::put('products/{product}/price-rules/{priceRule}', [ProductPriceRuleController::class, 'update']);
    Route::delete('products/{product}/price-rules/{priceRule}', [ProductPriceRuleController::class, 'destroy']);

    // ---- Variants ----
    Route::get('products/{product}/variants', [ProductVariantController::class, 'index']);
    Route::post('products/{product}/variants', [ProductVariantController::class, 'store']);
    Route::put('products/{product}/variants/{variant}', [ProductVariantController::class, 'update']);
    Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy']);

    // ---- Bundles ----
    Route::get('products/{product}/bundle', [ProductBundleController::class, 'show']);
    Route::post('products/{product}/bundle', [ProductBundleController::class, 'store']);
    Route::put('products/{product}/bundle', [ProductBundleController::class, 'update']);
    Route::delete('products/{product}/bundle', [ProductBundleController::class, 'destroy']);
    Route::post('pos/bundle-sale-items', [BundleSaleItemController::class, 'store']);

    // ---- Activity Logs ----
    Route::get('products/{product}/activity', [ProductActivityLogController::class, 'index']);

    // ---- Labels ----
    Route::get('products/{product}/label', [ProductLabelController::class, 'show']);
    Route::get('products/{product}/variants/{variant}/label', [ProductLabelController::class, 'variant']);

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

        // Subscription management
        Route::get('/admin/subscriptions', [AdminSubscriptionController::class, 'index']);
        Route::get('/admin/subscriptions/{subscription}', [AdminSubscriptionController::class, 'show']);
        Route::put('/admin/subscriptions/{subscription}/plan', [AdminSubscriptionController::class, 'changePlan']);
        Route::post('/admin/subscriptions/{subscription}/extend-trial', [AdminSubscriptionController::class, 'extendTrial']);
        Route::post('/admin/subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel']);

        // Payment verification
        Route::get('/admin/payments', [PaymentController::class, 'index']);
        Route::get('/admin/payments/{payment}', [PaymentController::class, 'show']);
        Route::post('/admin/payments/{payment}/approve', [PaymentController::class, 'approve']);
        Route::post('/admin/payments/{payment}/reject', [PaymentController::class, 'reject']);
    });
});
