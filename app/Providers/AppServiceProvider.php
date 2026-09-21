<?php

namespace App\Providers;

use App\Models\ProductUnit;
use App\Models\ProductBarcode;
use App\Observers\ProductUnitObserver;
use App\Observers\ProductBarcodeObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        ProductUnit::observe(ProductUnitObserver::class);
        ProductBarcode::observe(ProductBarcodeObserver::class);

        // Owners and superadmins bypass every Spatie permission check.
        // Feature permissions (employees.list.view, etc.) only ever
        // apply to staff/branch_head — an owner is never blocked by
        // their own shop's permission matrix.
        Gate::before(function ($user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            if ($user->isShopOwner()) {
                return true;
            }

            return null; // fall through to the normal Spatie check
        });
    }
}
