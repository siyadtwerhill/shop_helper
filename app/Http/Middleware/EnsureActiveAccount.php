<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->role === 'staff') {
            $staff = $user->staff;

            if (! $staff || $staff->status !== 'active' || ! $staff->shopOwner?->is_active) {
                $token = $user->currentAccessToken();
                if ($token instanceof PersonalAccessToken) {
                    $token->delete();
                }

                return response()->json([
                    'message' => 'Your account or shop is deactivated. Please contact your shop owner.',
                ], 403);
            }

            app(PermissionRegistrar::class)
                ->setPermissionsTeamId($staff->shop_owner_id);
        } elseif ($user && $user->role === 'shop_owner' && ! $user->shopOwner?->is_active) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json(['message' => 'Your shop is deactivated. Please contact support.'], 403);
        }

        return $next($request);
    }
}
