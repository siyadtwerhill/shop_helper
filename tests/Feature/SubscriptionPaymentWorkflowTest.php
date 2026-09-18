<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\ShopOwner;
use App\Models\Subscription;
use App\Models\SubscriptionChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_owner_can_create_pending_payment_upload_proof_and_keep_current_subscription(): void
    {
        Storage::fake('public');

        $freePlan = $this->createPlan('Free', 0);
        $proPlan = $this->createPlan('Pro', 19000);
        [$ownerUser, $shop, $subscription] = $this->createShopOwner($freePlan);

        Sanctum::actingAs($ownerUser, ['*'], 'sanctum');

        $initiateResponse = $this->postJson('/api/subscription/initiate', [
            'plan_id' => $proPlan->id,
            'method' => 'kbz_pay',
        ]);

        $initiateResponse->assertCreated()
            ->assertJsonPath('payment.status', 'pending_verification')
            ->assertJsonPath('payment.amount', 19000);

        $paymentId = $initiateResponse->json('payment.id');
        $payment = Payment::findOrFail($paymentId);

        $this->assertStringStartsWith('SP-', $payment->reference_code);
        $this->assertSame($freePlan->id, $subscription->fresh()->plan_id);
        $this->assertSame($freePlan->id, $shop->fresh()->plan_id);

        $proofResponse = $this->postJson("/api/subscription/payments/{$payment->id}/proof", [
            'proof' => UploadedFile::fake()->createWithContent('kbz-proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')),
        ]);

        $proofResponse->assertOk()
            ->assertJsonPath('message', 'Proof uploaded. Awaiting verification.');

        $payment->refresh();
        $this->assertNotNull($payment->proof_path);
        Storage::disk('public')->assertExists($payment->proof_path);

        $historyResponse = $this->getJson('/api/subscription/payments');

        $historyResponse->assertOk()
            ->assertJsonPath('payments.data.0.id', $payment->id)
            ->assertJsonPath('payments.data.0.status', 'pending_verification');

        $this->assertSame($freePlan->id, $subscription->fresh()->plan_id);
    }

    public function test_superadmin_payment_queue_lists_pending_payments_oldest_first(): void
    {
        $freePlan = $this->createPlan('Free', 0);
        $proPlan = $this->createPlan('Pro', 19000);
        [, $firstShop] = $this->createShopOwner($freePlan, 'First Shop');
        [, $secondShop] = $this->createShopOwner($freePlan, 'Second Shop');
        $admin = User::factory()->create(['role' => 'superadmin']);

        $newerPayment = $this->createPayment($secondShop, $proPlan, [
            'reference_code' => 'SP-NEWER',
        ]);
        $olderPayment = $this->createPayment($firstShop, $proPlan, [
            'reference_code' => 'SP-OLDER',
        ]);

        $newerPayment->forceFill([
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ])->save();
        $olderPayment->forceFill([
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ])->save();

        Sanctum::actingAs($admin, ['*'], 'sanctum');

        $response = $this->getJson('/api/admin/payments?status=pending_verification');

        $response->assertOk()
            ->assertJsonPath('pending_count', 2)
            ->assertJsonPath('payments.data.0.reference_code', 'SP-OLDER')
            ->assertJsonPath('payments.data.1.reference_code', 'SP-NEWER');
    }

    public function test_approving_payment_updates_payment_subscription_shop_profile_and_admin_subscription_list(): void
    {
        $freePlan = $this->createPlan('Free', 0);
        $proPlan = $this->createPlan('Pro', 19000);
        [$ownerUser, $shop, $subscription] = $this->createShopOwner($freePlan);
        $payment = $this->createPayment($shop, $proPlan, ['reference_code' => 'SP-APPROVE']);
        $admin = User::factory()->create(['role' => 'superadmin']);

        Sanctum::actingAs($admin, ['*'], 'sanctum');

        $response = $this->postJson("/api/admin/payments/{$payment->id}/approve");

        $response->assertOk()
            ->assertJsonPath('payment.status', 'approved')
            ->assertJsonPath('payment.verified_by.id', $admin->id)
            ->assertJsonPath('payment.shop_owner.subscription.plan.id', $proPlan->id);

        $payment->refresh();
        $subscription->refresh();
        $shop->refresh();

        $this->assertSame('approved', $payment->status);
        $this->assertSame($admin->id, $payment->verified_by_user_id);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame($proPlan->id, $subscription->plan_id);
        $this->assertSame('active', $subscription->status);
        $this->assertNotNull($subscription->current_period_end);
        $this->assertSame($proPlan->id, $shop->plan_id);

        $this->assertDatabaseHas('subscription_changes', [
            'subscription_id' => $subscription->id,
            'from_plan_id' => $freePlan->id,
            'to_plan_id' => $proPlan->id,
            'changed_by_user_id' => $admin->id,
            'reason' => 'Payment approved (ref: SP-APPROVE)',
        ]);

        $adminSubscriptionsResponse = $this->getJson('/api/admin/subscriptions');

        $adminSubscriptionsResponse->assertOk()
            ->assertJsonPath('subscriptions.data.0.id', $subscription->id)
            ->assertJsonPath('subscriptions.data.0.plan.id', $proPlan->id)
            ->assertJsonPath('subscriptions.data.0.shop_owner.plan_id', $proPlan->id);

        Sanctum::actingAs($ownerUser, ['*'], 'sanctum');

        $profileResponse = $this->getJson('/api/user');

        $profileResponse->assertOk()
            ->assertJsonPath('shop_owner.plan_id', $proPlan->id)
            ->assertJsonPath('shop_owner.plan.id', $proPlan->id)
            ->assertJsonPath('shop_owner.plan.name', 'Pro');
    }

    public function test_approving_first_payment_creates_missing_subscription(): void
    {
        $freePlan = $this->createPlan('Free', 0);
        $proPlan = $this->createPlan('Pro', 19000);
        [$ownerUser, $shop] = $this->createShopOwner($freePlan, createSubscription: false);
        $payment = $this->createPayment($shop, $proPlan, ['reference_code' => 'SP-FIRST']);
        $admin = User::factory()->create(['role' => 'superadmin']);

        Sanctum::actingAs($admin, ['*'], 'sanctum');

        $this->postJson("/api/admin/payments/{$payment->id}/approve")
            ->assertOk()
            ->assertJsonPath('payment.shop_owner.subscription.plan.id', $proPlan->id);

        $subscription = Subscription::where('shop_owner_id', $shop->id)->first();

        $this->assertNotNull($subscription);
        $this->assertSame($proPlan->id, $subscription->plan_id);
        $this->assertSame($proPlan->id, $shop->fresh()->plan_id);

        $this->assertDatabaseHas('subscription_changes', [
            'subscription_id' => $subscription->id,
            'from_plan_id' => null,
            'to_plan_id' => $proPlan->id,
        ]);

        Sanctum::actingAs($ownerUser, ['*'], 'sanctum');

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('shop_owner.plan.id', $proPlan->id);
    }

    public function test_rejecting_payment_requires_reason_and_allows_new_payment(): void
    {
        $freePlan = $this->createPlan('Free', 0);
        $proPlan = $this->createPlan('Pro', 19000);
        [$ownerUser, $shop, $subscription] = $this->createShopOwner($freePlan);
        $payment = $this->createPayment($shop, $proPlan, ['reference_code' => 'SP-REJECT']);
        $admin = User::factory()->create(['role' => 'superadmin']);

        Sanctum::actingAs($admin, ['*'], 'sanctum');

        $this->postJson("/api/admin/payments/{$payment->id}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rejection_reason');

        $this->postJson("/api/admin/payments/{$payment->id}/reject", [
            'rejection_reason' => 'Reference code not found in KBZPay history.',
        ])->assertOk()
            ->assertJsonPath('payment.status', 'rejected');

        $this->assertSame($freePlan->id, $subscription->fresh()->plan_id);
        $this->assertSame($freePlan->id, $shop->fresh()->plan_id);

        Sanctum::actingAs($ownerUser, ['*'], 'sanctum');

        $this->postJson('/api/subscription/initiate', [
            'plan_id' => $proPlan->id,
            'method' => 'wave_pay',
        ])->assertCreated()
            ->assertJsonPath('payment.status', 'pending_verification');

        $this->assertSame(2, Payment::where('shop_owner_id', $shop->id)->count());
    }

    private function createPlan(string $name, int $price): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => strtolower($name) . '-' . uniqid(),
            'description' => "{$name} plan",
            'price_monthly' => $price,
            'is_active' => true,
            'sort_order' => $price,
        ]);
    }

    private function createShopOwner(
        Plan $plan,
        string $shopName = 'Test Shop',
        bool $createSubscription = true
    ): array {
        $user = User::factory()->create(['role' => 'shop_owner']);

        $shop = ShopOwner::create([
            'user_id' => $user->id,
            'shop_name' => $shopName,
            'location' => 'Yangon',
            'plan_id' => $plan->id,
            'staff_count' => 0,
        ]);

        $subscription = null;
        if ($createSubscription) {
            $subscription = Subscription::create([
                'shop_owner_id' => $shop->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_end' => now()->addMonth(),
            ]);
        }

        return [$user, $shop, $subscription];
    }

    private function createPayment(ShopOwner $shop, Plan $plan, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'shop_owner_id' => $shop->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price_monthly,
            'method' => 'kbz_pay',
            'reference_code' => Payment::generateReferenceCode(),
            'status' => 'pending_verification',
            'expires_at' => now()->addHours(48),
        ], $overrides));
    }
}