<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BlueprintController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\InventoryMovementController;
use App\Http\Controllers\Api\ProductScannerController;
use App\Http\Controllers\Api\PriceQuoteController;
use App\Http\Controllers\Api\ProductPriceRuleController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\ProductBundleController;
use App\Http\Controllers\Api\ProductActivityLogController;
use App\Http\Controllers\Api\ProductLabelController;
use App\Http\Controllers\Api\ActivityFeedController;
use App\Http\Controllers\Api\BundleListController;
use App\Http\Controllers\Api\PosSaleController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\CustomerController;
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

Route::middleware(['auth:sanctum', 'active'])->get('/user', function (Request $request) {
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
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Only shop owner registration is public - staff are created by shop owners
Route::post('/shop-owner/register', [AuthController::class, 'shopOwnerRegister'])->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile/password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');

    // Shop-owner subscription management
    Route::get('/subscription', [SubscriptionController::class, 'show']);
    Route::get('/subscription/payments', [SubscriptionController::class, 'history']);
    Route::post('/subscription/initiate', [SubscriptionController::class, 'initiate']);
    Route::post('/subscription/payments/{payment}/proof', [SubscriptionController::class, 'uploadProof']);
    Route::post('/subscription/downgrade', [SubscriptionController::class, 'scheduleDowngrade']);

    Route::middleware('superadmin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::get('/users/role/{role}', [UserController::class, 'getUsersByRole']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // ---- Employee Management (shop_owner / branch_head) ----
    Route::middleware('can.perm:employees.view')->get('/employees', [EmployeeController::class, 'index']);
    Route::middleware('can.perm:employees.create')->post('/employees', [EmployeeController::class, 'store']);
    Route::middleware('can.perm:employees.edit')->group(function () {
        Route::put('/employees/{staff}', [EmployeeController::class, 'update']);
        Route::post('/employees/{staff}/reset-password', [EmployeeController::class, 'resetPassword']);
    });
    Route::middleware('can.perm:employees.delete')->delete('/employees/{staff}', [EmployeeController::class, 'destroy']);

    // ---- Roles & Permissions ----
    Route::middleware('can.perm:employees.roles.view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{role}/matrix', [RoleController::class, 'matrix']);
    });
    Route::middleware('can.perm:employees.roles.create')->post('/roles', [RoleController::class, 'store']);
    Route::middleware('can.perm:employees.roles.edit')->group(function () {
        Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
    });
    Route::middleware('can.perm:employees.roles.delete')->delete('/roles/{role}', [RoleController::class, 'destroy']);

    // ---- Branches ----
    Route::middleware('can.perm:branches.view')->get('/branches', [BranchController::class, 'index']);
    Route::middleware('can.perm:branches.create')->post('/branches', [BranchController::class, 'store']);
    Route::middleware('can.perm:branches.edit')->put('/branches/{branch}', [BranchController::class, 'update']);
    Route::middleware('can.perm:branches.delete')->delete('/branches/{branch}', [BranchController::class, 'destroy']);

    // ---- Products, Categories, Brands ----
    Route::middleware('can.perm:products.view')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::post('/products/lookup-barcode', [ProductController::class, 'lookupByBarcode']);
    });
    Route::middleware('can.perm:products.create')->post('/products', [ProductController::class, 'store']);
    Route::middleware('can.perm:products.edit')->group(function () {
        // POST is accepted so multipart FormData from the edit screen can be parsed
        // (PHP does not populate files/input on a real PUT).
        Route::match(['put', 'patch', 'post'], '/products/{product}', [ProductController::class, 'update']);
        Route::post('/products/{id}/restore', [ProductController::class, 'restore']);
    });
    Route::middleware('can.perm:products.delete')->delete('/products/{product}', [ProductController::class, 'destroy']);

    // ---- Blueprints ----
    Route::middleware('can.perm:products.view')->group(function () {
        Route::get('/blueprints', [BlueprintController::class, 'index']);
        Route::get('/blueprints/presets', [BlueprintController::class, 'presets']);
        Route::get('/blueprints/{blueprint}', [BlueprintController::class, 'show']);
        Route::get('/blueprints/{blueprint}/schema', [BlueprintController::class, 'schema']);
        Route::get('/blueprints/{blueprint}/form', [BlueprintController::class, 'form']);
    });
    Route::middleware('can.perm:products.create')->post('/blueprints', [BlueprintController::class, 'store']);
    Route::middleware('can.perm:products.edit')->group(function () {
        Route::put('/blueprints/{blueprint}', [BlueprintController::class, 'update']);
        Route::post('/blueprints/{blueprint}/duplicate', [BlueprintController::class, 'duplicate']);
    });
    Route::middleware('can.perm:products.delete')->delete('/blueprints/{blueprint}', [BlueprintController::class, 'destroy']);

    Route::middleware('can.perm:products.view')->apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::middleware('can.perm:products.create')->apiResource('categories', CategoryController::class)->only(['store']);
    Route::middleware('can.perm:products.edit')->apiResource('categories', CategoryController::class)->only(['update']);
    Route::middleware('can.perm:products.delete')->apiResource('categories', CategoryController::class)->only(['destroy']);

    Route::middleware('can.perm:products.view')->apiResource('brands', BrandController::class)->only(['index', 'show']);
    Route::middleware('can.perm:products.create')->apiResource('brands', BrandController::class)->only(['store']);
    Route::middleware('can.perm:products.edit')->apiResource('brands', BrandController::class)->only(['update']);
    Route::middleware('can.perm:products.delete')->apiResource('brands', BrandController::class)->only(['destroy']);

    // ---- Units & Product Units ----
    Route::middleware('can.perm:products.units.view')->apiResource('units', UnitController::class)->except(['show']);
    Route::middleware('can.perm:products.units.create')->apiResource('units', UnitController::class)->only(['store']);
    Route::middleware('can.perm:products.units.edit')->apiResource('units', UnitController::class)->only(['update']);
    Route::middleware('can.perm:products.units.delete')->apiResource('units', UnitController::class)->only(['destroy']);

    Route::middleware('can.perm:products.units.view')->get('products/{product}/units', [ProductUnitController::class, 'index']);
    Route::middleware('can.perm:products.units.create')->post('products/{product}/units', [ProductUnitController::class, 'store']);
    Route::middleware('can.perm:products.units.edit')->put('products/{product}/units/{productUnit}', [ProductUnitController::class, 'update']);
    Route::middleware('can.perm:products.units.delete')->delete('products/{product}/units/{productUnit}', [ProductUnitController::class, 'destroy']);

    // ---- Inventory Movements & Scanner ----
    Route::middleware('can.perm:products.view')->post('scanner/lookup', [ProductScannerController::class, 'lookup']);

    Route::middleware('can.perm:inventory.stock.view')->get('products/{product}/movements', [InventoryMovementController::class, 'index']);
    Route::middleware('can.perm:inventory.stock.edit')->post('products/{product}/movements', [InventoryMovementController::class, 'store']);

    // ---- Pricing ----
    Route::middleware('can.perm:products.pricing.view')->post('pos/price-quote', [PriceQuoteController::class, 'store']);

    Route::middleware('can.perm:products.pricing.view')->get('products/{product}/price-rules', [ProductPriceRuleController::class, 'index']);
    Route::middleware('can.perm:products.pricing.create')->post('products/{product}/price-rules', [ProductPriceRuleController::class, 'store']);
    Route::middleware('can.perm:products.pricing.edit')->put('products/{product}/price-rules/{priceRule}', [ProductPriceRuleController::class, 'update']);
    Route::middleware('can.perm:products.pricing.delete')->delete('products/{product}/price-rules/{priceRule}', [ProductPriceRuleController::class, 'destroy']);

    // ---- Variants ----
    Route::middleware('can.perm:products.variants.view')->group(function () {
        Route::get('products/{product}/variants', [ProductVariantController::class, 'index']);
        Route::get('products/{product}/variant-axes', [ProductVariantController::class, 'axes']);
    });
    Route::middleware('can.perm:products.variants.create')->group(function () {
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store']);
        Route::post('products/{product}/variants/bulk', [ProductVariantController::class, 'bulk']);
    });
    Route::middleware('can.perm:products.variants.edit')->group(function () {
        Route::put('products/{product}/variants/{variant}', [ProductVariantController::class, 'update']);
        Route::post('products/{product}/variants/{variant}/assign-stock', [ProductVariantController::class, 'assignStock']);
    });
    Route::middleware('can.perm:products.variants.delete')->delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy']);

    // ---- Bundles ----
    Route::middleware('can.perm:products.bundles.view')->get('products/{product}/bundle', [ProductBundleController::class, 'show']);
    Route::middleware('can.perm:products.bundles.create')->post('products/{product}/bundle', [ProductBundleController::class, 'store']);
    Route::middleware('can.perm:products.bundles.edit')->put('products/{product}/bundle', [ProductBundleController::class, 'update']);
    Route::middleware('can.perm:products.bundles.delete')->delete('products/{product}/bundle', [ProductBundleController::class, 'destroy']);

    // ---- Activity Logs ----
    Route::middleware('can.perm:products.view')->get('products/{product}/activity', [ProductActivityLogController::class, 'index']);

    // ---- Labels ----
    Route::middleware('can.perm:products.view')->group(function () {
        Route::get('products/{product}/label', [ProductLabelController::class, 'show']);
        Route::get('products/{product}/variants/{variant}/label', [ProductLabelController::class, 'variant']);
    });

    // ---- Global Activity Feed ----
    Route::middleware('can.perm:products.view')->get('activity', [ActivityFeedController::class, 'index']);

    // ---- All Bundles ----
    Route::middleware('can.perm:products.bundles.view')->get('bundles', [BundleListController::class, 'index']);

    // ---- POS Sales ----
    Route::middleware('can.perm:sales.pos')->post('pos/sales', [PosSaleController::class, 'store']);

    // ---- Sales ----
    Route::middleware('can.perm:sales.view')->group(function () {
        Route::get('sales/overview', [SalesController::class, 'overview']);
        Route::get('sales', [SalesController::class, 'index']);
        Route::get('sales/{sale}', [SalesController::class, 'show']);
    });
    Route::middleware('can.perm:sales.refunds')->post('sales/{sale}/void', [SalesController::class, 'void']);

    // ---- Customers ----
    Route::middleware('can.perm:customers.view')->apiResource('customers', CustomerController::class)->only(['index', 'show']);
    Route::middleware('can.perm:customers.create')->apiResource('customers', CustomerController::class)->only(['store']);
    Route::middleware('can.perm:customers.edit')->apiResource('customers', CustomerController::class)->only(['update']);
    Route::middleware('can.perm:customers.delete')->apiResource('customers', CustomerController::class)->only(['destroy']);

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
