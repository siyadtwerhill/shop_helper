<?php

namespace Tests\Feature;

use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use App\Models\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_staff_login_returns_403(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->for($shop)->for($user)->create(['status' => 'inactive']);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Your account is deactivated. Please contact your shop owner.');
    }

    public function test_old_token_for_deactivated_staff_returns_403(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->for($shop)->for($user)->create(['status' => 'active']);

        $token = $user->createToken('auth-token')->plainTextToken;
        $staff->update(['status' => 'inactive']);

        $response = $this->withToken($token)->getJson('/api/user');

        $response->assertStatus(403);
    }

    public function test_registration_creates_default_blueprint(): void
    {
        \App\Models\Plan::create([
            'name' => 'Free',
            'slug' => 'free',
            'max_staff' => 3,
            'max_branches' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->postJson('/api/shop-owner/register', [
            'owner_name' => 'Test Owner',
            'shop_name' => 'Test Shop',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);
        $shopId = $response->json('shop_owner.id');

        $this->assertDatabaseHas('blueprints', [
            'shop_owner_id' => $shopId,
            'name' => 'Generic',
        ]);
    }

    public function test_11th_login_attempt_returns_429(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com', 'password' => bcrypt('password')]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'wrong'])->assertStatus(401);
        }

        $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner', 'password' => bcrypt('oldpassword')]);
        $shop->user()->associate($user);
        $shop->save();

        $token = $user->createToken('auth-token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/profile/password', [
            'current_password' => 'wrong',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Current password is incorrect.');
    }
}
