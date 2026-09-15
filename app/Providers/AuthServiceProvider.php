<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Bypass permission checks for superadmins and shop owners
        // This mirrors the frontend logic where isUnrestricted users see everything
        Gate::before(function ($user, $ability) {
            if ($user->role === 'superadmin' || $user->role === 'shop_owner') {
                return true;
            }
            return null;
        });
    }
}
