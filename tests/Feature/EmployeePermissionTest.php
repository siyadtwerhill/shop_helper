<?php

namespace Tests\Feature;

use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_employees_returns_temporary_password(): void
    {
        $shop = ShopOwner::factory()->create();
        $user = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($user);
        $shop->save();

        app(\App\Services\BlueprintPresetService::class)->createGenericBlueprint($shop);

        $role = Role::create(['name' => 'Cashier', 'shop_owner_id' => $shop->id]);

        $response = $this->actingAs($user)->postJson('/api/employees', [
            'name' => 'Test Staff',
            'email' => 'staff@example.com',
            'phone' => '1234567890',
            'role_id' => $role->id,
        ]);

        $response->assertStatus(201);
        $this->assertArrayHasKey('temporary_password', $response->json());
        $this->assertNotEquals('12345678', $response->json('temporary_password'));
    }

    public function test_remove_action_deactivates_employee(): void
    {
        $shop = ShopOwner::factory()->create();
        $owner = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($owner);
        $shop->save();

        $staffUser = User::factory()->create(['role' => 'staff']);
        $staff = Staff::factory()->for($shop)->for($staffUser)->create(['status' => 'active']);

        $this->actingAs($owner)->deleteJson("/api/employees/{$staff->id}")->assertOk();

        $this->assertDatabaseHas('users', ['id' => $staffUser->id]);
        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'status' => 'inactive']);
    }
}
