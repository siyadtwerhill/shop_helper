<?php

namespace Database\Seeders;

use App\Models\ShopOwner;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class BackfillSubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        ShopOwner::whereDoesntHave('subscription')->each(function (ShopOwner $shop) {
            Subscription::create([
                'shop_owner_id' => $shop->id,
                'plan_id' => $shop->plan_id ?? 1,
                'status' => 'active',
                'current_period_end' => now()->addMonth(),
            ]);
        });
    }
}
