<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubscriptionController extends Controller
{
    private function shop(Request $request)
    {
        return $request->user()->shopOwner;
    }

    public function show(Request $request)
    {
        $shop = $this->shop($request);

        return response()->json([
            'subscription' => $shop->subscription()->with(['plan', 'pendingPlan'])->first(),
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
            'pending_payment' => Payment::where('shop_owner_id', $shop->id)
                ->where('status', 'pending_verification')
                ->latest()
                ->first(),
            'current_plan' => $shop->plan,
        ]);
    }

    public function history(Request $request)
    {
        $shop = $this->shop($request);

        return response()->json([
            'payments' => Payment::where('shop_owner_id', $shop->id)->with('plan')->latest()->paginate(15),
        ]);
    }

    /**
     * Step 1 of the flow: shop owner picks a plan, we generate a
     * reference code and show them where to send money. No proof
     * uploaded yet — that's a separate step so they can come back
     * to it after making the transfer.
     */
    public function initiate(Request $request)
    {
        $shop = $this->shop($request);

        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'method' => 'required|in:bank_transfer,kbz_pay,wave_pay,other',
        ]);

        // Block a second pending payment while one is already open —
        // avoids duplicate reference codes floating around for one shop.
        $existing = Payment::where('shop_owner_id', $shop->id)
            ->where('status', 'pending_verification')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You already have a payment awaiting verification.',
                'payment' => $existing,
            ], 422);
        }

        $plan = Plan::findOrFail($data['plan_id']);

        $payment = Payment::create([
            'shop_owner_id' => $shop->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price_monthly,
            'method' => $data['method'],
            'reference_code' => Payment::generateReferenceCode(),
            'status' => 'pending_verification',
            'expires_at' => now()->addHours(48),
        ]);

        return response()->json(['payment' => $payment], 201);
    }

    /**
     * Step 2: shop owner uploads proof after making the transfer.
     */
    public function uploadProof(Request $request, Payment $payment)
    {
        $shop = $this->shop($request);
        abort_if($payment->shop_owner_id !== $shop->id, 403);
        abort_if(!$payment->isPending(), 422, 'This payment is no longer pending.');

        $request->validate(['proof' => 'required|image|max:4096']);

        if ($payment->proof_path) {
            Storage::disk('public')->delete($payment->proof_path);
        }

        $path = $request->file('proof')->store('payment-proofs', 'public');
        $payment->update(['proof_path' => $path]);

        return response()->json(['message' => 'Proof uploaded. Awaiting verification.', 'payment' => $payment]);
    }

    /**
     * Downgrade — no money involved, so it never touches Payment.
     * Takes effect at the end of the current billing period instead
     * of immediately, so the shop keeps what they already paid for.
     */
    public function scheduleDowngrade(Request $request)
    {
        $shop = $this->shop($request);
        $subscription = $shop->subscription;

        $data = $request->validate(['plan_id' => 'required|exists:plans,id']);

        $targetPlan = Plan::findOrFail($data['plan_id']);
        if ($targetPlan->price_monthly >= $subscription->plan->price_monthly) {
            return response()->json(['message' => 'Use the upgrade flow for this plan change.'], 422);
        }

        $subscription->update(['pending_plan_id' => $targetPlan->id]);

        return response()->json([
            'message' => "Downgrade to {$targetPlan->name} scheduled for " . $subscription->current_period_end->toDateString(),
        ]);
    }
}
