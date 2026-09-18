<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionChange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['shopOwner', 'plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending_verification'); // default: the queue
        }

        return response()->json([
            'payments' => $query->oldest()->paginate(20),
            'pending_count' => Payment::where('status', 'pending_verification')->count(),
        ]);
    }

    public function show(Payment $payment)
    {
        return response()->json(['payment' => $payment->load(['shopOwner.subscription.plan', 'plan'])]);
    }

    public function approve(Request $request, Payment $payment)
    {
        abort_if(!$payment->isPending(), 422, 'This payment has already been processed.');

        $payment = DB::transaction(function () use ($request, $payment) {
            $payment->update([
                'status' => 'approved',
                'verified_by_user_id' => $request->user()->id,
                'verified_at' => now(),
            ]);

            $shop = $payment->shopOwner;
            abort_if(!$shop, 422, 'This payment is not linked to a shop.');

            $subscription = $shop->subscription;
            $fromPlanId = $subscription?->plan_id;

            if ($subscription) {
                $subscription->update([
                    'plan_id' => $payment->plan_id,
                    'status' => 'active',
                    'current_period_end' => now()->addMonth(),
                    'pending_plan_id' => null, // clear any scheduled downgrade: they just paid for a different plan
                ]);
            } else {
                $subscription = Subscription::create([
                    'shop_owner_id' => $shop->id,
                    'plan_id' => $payment->plan_id,
                    'status' => 'active',
                    'current_period_end' => now()->addMonth(),
                ]);
            }

            $shop->update(['plan_id' => $payment->plan_id]);

            SubscriptionChange::create([
                'subscription_id' => $subscription->id,
                'from_plan_id' => $fromPlanId,
                'to_plan_id' => $payment->plan_id,
                'changed_by_user_id' => $request->user()->id,
                'reason' => "Payment approved (ref: {$payment->reference_code})",
            ]);

            return $payment->fresh(['shopOwner.subscription.plan', 'plan', 'verifiedBy']);
        });

        // Notification::send($payment->shopOwner->user, new PaymentApprovedNotification($payment));

        return response()->json(['message' => 'Payment approved. Subscription updated.', 'payment' => $payment]);
    }

    public function reject(Request $request, Payment $payment)
    {
        abort_if(!$payment->isPending(), 422, 'This payment has already been processed.');

        $data = $request->validate(['rejection_reason' => 'required|string|max:255']);

        $payment->update([
            'status' => 'rejected',
            'verified_by_user_id' => $request->user()->id,
            'verified_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        // Notification::send($payment->shopOwner->user, new PaymentRejectedNotification($payment));

        return response()->json(['message' => 'Payment rejected.', 'payment' => $payment]);
    }
}
