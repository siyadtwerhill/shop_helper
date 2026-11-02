<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\BranchModeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            DB::transaction(function () use ($sub) {
                $locked = Subscription::whereKey($sub->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->pending_plan_id || $locked->current_period_end?->isFuture()) return;

                $shop = $locked->shopOwner()->lockForUpdate()->firstOrFail();
                $shop->update(['plan_id' => $locked->pending_plan_id]);
                $locked->update([
                    'plan_id' => $locked->pending_plan_id,
                    'pending_plan_id' => null,
                    'current_period_end' => now()->addMonth(),
                ]);
                app(BranchModeService::class)->ensureInitialBranch($shop->fresh('plan'));
            });
        }

        $this->info("Applied {$subscriptions->count()} scheduled downgrades.");
    }
}
