<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire subscriptions past their grace period';

    public function handle(): void
    {
        $expired = Subscription::where('status', 'past_due')
            ->where('current_period_end', '<', now()->subDays(3)) // 3-day grace period
            ->get();

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired', 'expired_at' => now()]);
        }

        $this->info("Expired {$expired->count()} subscriptions.");
    }
}
