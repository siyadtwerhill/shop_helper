<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait ResolvesShop
{
    /**
     * Resolve the shop for the current user. Shop owners have it directly;
     * staff / branch heads reach it through their staff record.
     */
    protected function shop(Request $request)
    {
        $user = $request->user();
        $shop = $user->shopOwner ?? $user->staff?->shopOwner;

        abort_unless($shop, 403, 'No shop is linked to this account.');

        return $shop;
    }
}
