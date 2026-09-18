<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;

class ApplyScheduledDowngrades extends Command
{
    protected $signature = 'subscriptions:apply-downgrades';
    protected $description = 'Apply scheduled downgrades once the current billing period ends';

    public function handle(): void
    {
        $subscriptions = Subscription::whereNotNull('pending_plan_id')
            ->where('current_period_end', '<=', now())
            ->get();

        foreach ($subscriptions as $sub) {
            $sub->shopOwner->update(['plan_id' => $sub->pending_plan_id]);
            $sub->update([
                'plan_id' => $sub->pending_plan_id,
                'pending_plan_id' => null,
                'current_period_end' => now()->addMonth(),
            ]);
        }

        $this->info("Applied {$subscriptions->count()} scheduled downgrades.");
    }
}
