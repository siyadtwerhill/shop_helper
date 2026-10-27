<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionChange;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->currentSubscriptions()->with(['shopOwner', 'plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $query->whereHas('shopOwner', fn ($q) => $q->where('shop_name', 'like', "%{$term}%"));
        }

        $subscriptions = $query->latest()->paginate(20);

        return response()->json([
            'subscriptions' => $subscriptions,
            'stats' => [
                'active_count' => $this->currentSubscriptions()->where('subscriptions.status', 'active')->count(),
                'mrr' => $this->currentSubscriptions()->where('subscriptions.status', 'active')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')->sum('plans.price_monthly'),
                'trialing_count' => $this->currentSubscriptions()->where('subscriptions.status', 'trialing')->count(),
                'at_risk_count' => $this->currentSubscriptions()->where('subscriptions.status', 'past_due')
                    ->orWhere(fn ($q) => $q->where('subscriptions.current_period_end', '<=', now()->addDays(7))->whereNotIn('subscriptions.status', ['cancelled', 'expired']))
                    ->count(),
                'expired_this_month' => $this->currentSubscriptions()->where('subscriptions.status', 'expired')
                    ->whereMonth('subscriptions.expired_at', now()->month)
                    ->whereYear('subscriptions.expired_at', now()->year)
                    ->count(),
            ],
        ]);
    }

    private function currentSubscriptions()
    {
        $latestIds = Subscription::selectRaw('MAX(subscriptions.id)')->groupBy('subscriptions.shop_owner_id');

        return Subscription::whereIn('subscriptions.id', $latestIds);
    }

    public function show(Subscription $subscription)
    {
        return response()->json([
            'subscription' => $subscription->load(['shopOwner', 'plan', 'changes.fromPlan', 'changes.toPlan', 'changes.changedBy']),
        ]);
    }

    public function changePlan(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'reason' => 'nullable|string|max:255',
        ]);

        $fromPlanId = $subscription->plan_id;

        $subscription->update(['plan_id' => $data['plan_id']]);
        $subscription->shopOwner->update(['plan_id' => $data['plan_id']]); // keep convenience column in sync

        SubscriptionChange::create([
            'subscription_id' => $subscription->id,
            'from_plan_id' => $fromPlanId,
            'to_plan_id' => $data['plan_id'],
            'changed_by_user_id' => $request->user()->id,
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['message' => 'Plan changed.', 'subscription' => $subscription->fresh('plan')]);
    }

    public function extendTrial(Request $request, Subscription $subscription)
    {
        $data = $request->validate(['days' => 'required|integer|min:1|max:90']);

        $base = $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture()
            ? $subscription->trial_ends_at
            : now();

        $subscription->update(['trial_ends_at' => $base->addDays($data['days'])]);

        return response()->json(['message' => 'Trial extended.', 'subscription' => $subscription]);
    }

    public function cancel(Subscription $subscription)
    {
        $subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return response()->json(['message' => 'Subscription cancelled.', 'subscription' => $subscription]);
    }
}
