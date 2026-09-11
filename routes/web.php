<?php

use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\PlanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'ShopPilot API Backend',
        'status' => 'operational',
        'sanctum' => 'installed'
    ]);
});

Route::middleware(['auth', 'superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('plans', PlanController::class)->except('show');
    Route::resource('modules', ModuleController::class)->only(['index', 'create', 'store', 'show', 'update', 'destroy']);
    Route::post('modules/{module}/features', [FeatureController::class, 'store'])->name('modules.features.store');
    Route::put('features/{feature}', [FeatureController::class, 'update'])->name('features.update');
    Route::delete('features/{feature}', [FeatureController::class, 'destroy'])->name('features.destroy');
});
