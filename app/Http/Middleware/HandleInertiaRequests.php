<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'permissions' => $this->getUserPermissions($request->user()),
                ] : null,
            ],
        ];
    }

    protected function getUserPermissions($user)
    {
        if (!$user) return [];

        // Shop owners and superadmins have all permissions (return empty array to indicate full access)
        if ($user->role === 'shop_owner' || $user->role === 'superadmin') {
            return [];
        }

        // Staff get their assigned permissions
        if ($user->role === 'staff' && $user->staff) {
            $shopId = $user->staff->shop_owner_id;
            if ($shopId) {
                \Spatie\Permission\PermissionRegistrar::setPermissionsTeamId($shopId);
            }
            return $user->getAllPermissions()->pluck('name')->toArray();
        }

        return [];
    }
}
