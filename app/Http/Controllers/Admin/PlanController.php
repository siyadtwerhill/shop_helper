<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Plan;
use App\Models\ShopOwner;
use App\Models\Subscription;
use App\Models\SubscriptionChange;
use App\Services\BranchModeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return response()->json([
            'plans' => Plan::with('specificShop:id,shop_name')->withCount('modules')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $plan = DB::transaction(function () use ($data, $request) {
            $plan = Plan::create(collect($data)->except(['module_ids', 'subscription_months'])->toArray());
            $plan->modules()->sync($data['module_ids'] ?? []);

            if ($plan->scope === 'specific') {
                $this->assignSpecificPlan($plan, $request->user()->id, $data['subscription_months']);
            }

            return $plan;
        });

        return response()->json(['plan' => $plan->load('modules:id', 'specificShop:id,shop_name'), 'message' => 'Plan created.'], 201);
    }

    public function show(Plan $plan)
    {
        return response()->json([
            'plan' => $plan->load('modules:id', 'specificShop:id,shop_name'),
            'modules' => Module::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $this->validateData($request, $plan->id);

        if ($data['scope'] !== $plan->scope || (int) ($data['shop_owner_id'] ?? 0) !== (int) ($plan->shop_owner_id ?? 0)) {
            return response()->json(['message' => 'Plan availability cannot be changed after creation. Create a new plan to change its audience.'], 422);
        }

        DB::transaction(function () use ($plan, $data) {
            $plan->update(collect($data)->except(['module_ids', 'subscription_months'])->toArray());
            $plan->modules()->sync($data['module_ids'] ?? []);

            $shops = ShopOwner::where('plan_id', $plan->id)->lockForUpdate()->get();
            foreach ($shops as $shop) {
                app(BranchModeService::class)->ensureInitialBranch($shop->fresh('plan'));
            }
        });

        return response()->json(['plan' => $plan->load('modules:id', 'specificShop:id,shop_name'), 'message' => 'Plan updated.']);
    }

    public function destroy(Request $request, Plan $plan)
    {
        $result = DB::transaction(function () use ($request, $plan) {
            $plan = Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $shopIds = ShopOwner::where('plan_id', $plan->id)->pluck('id')
                ->merge(Subscription::where('plan_id', $plan->id)->pluck('shop_owner_id'))
                ->unique()
                ->values();
            $shops = ShopOwner::whereIn('id', $shopIds)->lockForUpdate()->get();

            if ($shops->contains(fn (ShopOwner $shop) => $shop->is_active)) {
                return ['error' => 'Cannot delete a plan assigned to active shops. Suspend or reassign those shops first.'];
            }

            $freePlan = $shops->isEmpty() ? null : Plan::where('slug', 'free')
                ->where('is_active', true)
                ->where('id', '!=', $plan->id)
                ->first();

            if ($shops->isNotEmpty() && ! $freePlan) {
                return ['error' => 'Cannot delete this plan because no active Free plan is available for suspended shops.'];
            }

            foreach ($shops as $shop) {
                $currentSubscription = $shop->subscription()->lockForUpdate()->first();
                $needsFallback = $shop->plan_id === $plan->id || $currentSubscription?->plan_id === $plan->id;

                if ($needsFallback) {
                    $fromPlanId = $currentSubscription?->plan_id ?? $shop->plan_id;
                    $shop->update(['plan_id' => $freePlan->id]);

                    if ($currentSubscription) {
                        $currentSubscription->update([
                            'plan_id' => $freePlan->id,
                            'status' => 'active',
                            'trial_ends_at' => null,
                            'current_period_end' => null,
                            'cancelled_at' => null,
                            'expired_at' => null,
                            'pending_plan_id' => null,
                        ]);

                        if ($fromPlanId !== $freePlan->id) {
                            SubscriptionChange::create([
                                'subscription_id' => $currentSubscription->id,
                                'from_plan_id' => $fromPlanId,
                                'to_plan_id' => $freePlan->id,
                                'changed_by_user_id' => $request->user()->id,
                                'reason' => 'Suspended shop moved to Free because its plan was deleted.',
                            ]);
                        }
                    }
                }

                // Old subscription rows can also reference the plan and must be detached before deletion.
                Subscription::where('shop_owner_id', $shop->id)
                    ->where('plan_id', $plan->id)
                    ->update(['plan_id' => $freePlan->id]);
            }

            $plan->delete();

            return null;
        });

        if ($result) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['message' => 'Plan deleted.']);
    }

    private function validateData(Request $request, ?int $planId = null): array
    {
        $plan = $planId ? Plan::findOrFail($planId) : null;
        $request->merge([
            'scope' => $request->input('scope', $plan?->scope ?? 'all'),
            'shop_owner_id' => $request->input('shop_owner_id', $plan?->shop_owner_id),
            'subscription_months' => $request->input('subscription_months'),
        ]);

        $slugRule = $planId
            ? "required|string|max:255|unique:plans,slug,{$planId}"
            : 'required|string|max:255|unique:plans';

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => $slugRule,
            'scope' => 'required|in:all,specific',
            'shop_owner_id' => 'nullable|required_if:scope,specific|exists:shop_owners,id',
            'subscription_months' => 'nullable|required_if:scope,specific|integer|min:1|max:120',
            'description' => 'nullable|string',
            'price_monthly' => 'required|integer|min:0',
            'max_staff' => 'nullable|integer|min:1',
            'max_branches' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'module_ids' => 'array',
            'module_ids.*' => 'exists:modules,id',
        ]);

        if ($data['scope'] === 'all') $data['shop_owner_id'] = null;

        return $data;
    }

    private function assignSpecificPlan(Plan $plan, int $adminId, int $months): void
    {
        $shop = ShopOwner::lockForUpdate()->findOrFail($plan->shop_owner_id);
        $subscription = $shop->subscription;
        $fromPlanId = $subscription?->plan_id ?? $shop->plan_id;
        $periodEnd = now()->addMonthsNoOverflow($months);

        if ($subscription) {
            $subscription->update([
                'plan_id' => $plan->id,
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_end' => $periodEnd,
                'cancelled_at' => null,
                'expired_at' => null,
                'pending_plan_id' => null,
            ]);
        } else {
            $subscription = Subscription::create([
                'shop_owner_id' => $shop->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_end' => $periodEnd,
            ]);
        }

        $shop->update(['plan_id' => $plan->id]);
        app(BranchModeService::class)->ensureInitialBranch($shop->fresh('plan'));

        if ($fromPlanId !== $plan->id) {
            SubscriptionChange::create([
                'subscription_id' => $subscription->id,
                'from_plan_id' => $fromPlanId,
                'to_plan_id' => $plan->id,
                'changed_by_user_id' => $adminId,
                'reason' => 'Specific plan assigned by admin.',
            ]);
        }
    }
}
