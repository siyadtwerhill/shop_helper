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

            if (! $staff || $staff->status !== 'active') {
                $token = $user->currentAccessToken();
                if ($token instanceof PersonalAccessToken) {
                    $token->delete();
                }

                return response()->json([
                    'message' => 'Your account is deactivated. Please contact your shop owner.',
                ], 403);
            }

            app(PermissionRegistrar::class)
                ->setPermissionsTeamId($staff->shop_owner_id);
        }

        return $next($request);
    }
}
