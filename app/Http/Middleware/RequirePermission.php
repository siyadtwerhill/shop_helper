<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Let shop owners and super-admins through unconditionally.
     * For staff, load their team context then check the named permission.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->isShopOwner() || $user->isSuperAdmin()) {
            return $next($request);
        }

        // Spatie uses team IDs to scope permissions; staff belong to one shop.
        $shopId = $user->staff?->shop_owner_id;
        if ($shopId) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($shopId);
        }

        // Clear cached relations so the correct team's roles are used.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        abort_unless($user->can($permission), 403, 'You do not have permission to do this.');

        return $next($request);
    }
}
