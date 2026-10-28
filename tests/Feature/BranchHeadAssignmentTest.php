<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchHeadAssignmentHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Models\ShopOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BranchHeadAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private ShopOwner $shopOwner;
    private User $shopOwnerUser;
    private Branch $branch1;
    private Branch $branch2;
    private Staff $staff1;
    private User $staff1User;
    private Staff $staff2;
    private User $staff2User;
    private Role $cashierRole;
    private Role $branchHeadRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Set team context to null initially
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        // Create shop owner
        $this->shopOwnerUser = User::create([
            'name' => 'Shop Owner',
            'username' => 'shop_owner',
            'email' => 'owner@shop.com',
            'role' => 'shop_owner',
            'password' => Hash::make('password'),
        ]);

        $this->shopOwner = ShopOwner::create([
            'user_id' => $this->shopOwnerUser->id,
            'shop_name' => 'Test Shop',
            'location' => 'Test Location',
        ]);

        // Create two branches
        $this->branch1 = Branch::create([
            'shop_owner_id' => $this->shopOwner->id,
            'name' => 'Branch 1',
            'address' => '123 Branch 1 St',
            'is_active' => true,
        ]);

        $this->branch2 = Branch::create([
            'shop_owner_id' => $this->shopOwner->id,
            'name' => 'Branch 2',
            'address' => '456 Branch 2 St',
            'is_active' => true,
        ]);

        // Create staff members
        $this->staff1User = User::create([
            'name' => 'Staff 1',
            'username' => 'staff_1',
            'email' => 'staff1@shop.com',
            'role' => 'staff',
            'password' => Hash::make('password'),
        ]);

        $this->staff1 = Staff::create([
            'user_id' => $this->staff1User->id,
            'shop_owner_id' => $this->shopOwner->id,
            'branch_id' => $this->branch1->id,
            'status' => 'active',
        ]);

        $this->staff2User = User::create([
            'name' => 'Staff 2',
            'username' => 'staff_2',
            'email' => 'staff2@shop.com',
            'role' => 'staff',
            'password' => Hash::make('password'),
        ]);

        $this->staff2 = Staff::create([
            'user_id' => $this->staff2User->id,
            'shop_owner_id' => $this->shopOwner->id,
            'branch_id' => $this->branch2->id,
            'status' => 'active',
        ]);

        // Create roles with team context
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->shopOwner->id);

        $this->cashierRole = Role::create([
            'name' => 'Cashier',
            'guard_name' => 'web',
            'shop_owner_id' => $this->shopOwner->id,
        ]);

        $this->branchHeadRole = Role::create([
            'name' => 'Branch Head',
            'guard_name' => 'web',
            'shop_owner_id' => $this->shopOwner->id,
            'is_system' => true,
        ]);
    }

    public function test_shop_owner_can_assign_branch_head()
    {
        $response = $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff1->id,
            ]);

        $response->assertStatus(200);
        
        $this->branch1->refresh();
        $this->assertEquals($this->staff1->id, $this->branch1->head_staff_id);
        
        $this->staff1->refresh();
        $this->assertEquals($this->branch1->id, $this->staff1->branch_id);
        
        $this->assertTrue($this->staff1User->hasRole('Branch Head'));
        $this->assertTrue($this->staff1->isBranchHead());
    }

    public function test_shop_owner_cannot_assign_an_employee_from_another_branch_as_head()
    {
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff2->id,
            ])
            ->assertUnprocessable();

        $this->assertNull($this->branch1->fresh()->head_staff_id);
        $this->assertSame($this->branch2->id, $this->staff2->fresh()->branch_id);
        $this->assertFalse($this->staff2User->fresh()->hasRole('Branch Head'));
    }

    public function test_new_branch_cannot_be_created_with_a_head_before_employee_assignment()
    {
        $this->actingAs($this->shopOwnerUser)
            ->postJson('/api/branches', [
                'name' => 'New Branch',
                'head_staff_id' => $this->staff1->id,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('branches', [
            'shop_owner_id' => $this->shopOwner->id,
            'name' => 'New Branch',
        ]);
    }

    public function test_employee_list_can_be_filtered_to_a_branch_for_head_selection()
    {
        $this->actingAs($this->shopOwnerUser)
            ->getJson("/api/employees?branch_id={$this->branch1->id}&per_page=100")
            ->assertOk()
            ->assertJsonCount(1, 'employees.data')
            ->assertJsonPath('employees.data.0.id', $this->staff1->id);
    }

    public function test_assignment_replaces_existing_roles_with_branch_head()
    {
        // Give staff1 Cashier role first
        $this->staff1User->assignRole($this->cashierRole);
        $this->assertTrue($this->staff1User->hasRole('Cashier'));

        // Assign as branch head
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff1->id,
            ]);

        // Assignment replaces the previous role instead of combining permissions.
        $assignedUser = $this->staff1User->fresh();
        $this->assertTrue($assignedUser->hasRole('Branch Head'));
        $this->assertFalse($assignedUser->hasRole('Cashier'));
        $this->assertCount(1, $assignedUser->roles);
    }

    public function test_replacement_removes_previous_head_role_only()
    {
        // Assign staff1 as branch head with Cashier role
        $this->staff1User->assignRole($this->cashierRole);
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff1->id,
            ]);

        // Assign staff2 with Cashier role
        $this->staff2User->assignRole($this->cashierRole);
        // A Branch Head candidate must already belong to the target branch.
        $this->staff2->update(['branch_id' => $this->branch1->id]);

        // Replace with staff2
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff2->id,
            ]);

        // The outgoing head loses the appointment role; its previous role was replaced.
        $this->assertFalse($this->staff1User->fresh()->hasRole('Branch Head'));
        $this->assertFalse($this->staff1User->fresh()->hasRole('Cashier'));

        // The incoming head has only the appointment role.
        $this->assertTrue($this->staff2User->fresh()->hasRole('Branch Head'));
        $this->assertFalse($this->staff2User->fresh()->hasRole('Cashier'));
    }

    public function test_removal_clears_head_staff_id_and_role()
    {
        // Assign staff1 as branch head
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff1->id,
            ]);

        // Remove by setting null
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => null,
            ]);

        $this->branch1->refresh();
        $this->assertNull($this->branch1->head_staff_id);
        
        $this->assertFalse($this->staff1User->fresh()->hasRole('Branch Head'));
        $this->assertFalse($this->staff1->fresh()->isBranchHead());
    }

    public function test_audit_history_is_logged()
    {
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff1->id,
            ]);

        $history = BranchHeadAssignmentHistory::where('branch_id', $this->branch1->id)->first();
        
        $this->assertNotNull($history);
        $this->assertEquals('assigned', $history->action);
        $this->assertEquals($this->staff1->id, $history->new_head_staff_id);
        $this->assertEquals($this->shopOwnerUser->id, $history->assigned_by_user_id);
    }

    public function test_moving_a_head_requires_reassignment_to_the_new_branch_first()
    {
        $this->staff1User->assignRole($this->cashierRole);

        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", ['head_staff_id' => $this->staff1->id])
            ->assertOk();

        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", ['head_staff_id' => null])
            ->assertOk();

        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/employees/{$this->staff1->id}", ['branch_id' => $this->branch2->id])
            ->assertOk();

        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch2->id}", ['head_staff_id' => $this->staff1->id])
            ->assertOk();

        $this->assertNull($this->branch1->fresh()->head_staff_id);
        $this->assertSame($this->staff1->id, $this->branch2->fresh()->head_staff_id);
        $this->assertSame($this->branch2->id, $this->staff1->fresh()->branch_id);
        $this->assertTrue($this->staff1User->fresh()->hasRole('Branch Head'));
        $this->assertFalse($this->staff1User->fresh()->hasRole('Cashier'));
        $this->assertCount(1, $this->staff1User->fresh()->roles);
        $this->assertDatabaseHas('branch_head_assignment_histories', [
            'branch_id' => $this->branch1->id,
            'previous_head_staff_id' => $this->staff1->id,
            'new_head_staff_id' => null,
            'action' => 'removed',
        ]);
    }

    public function test_assignment_and_history_roll_back_together()
    {
        $event = 'eloquent.creating: ' . BranchHeadAssignmentHistory::class;
        Event::listen($event, static function () {
            throw new \RuntimeException('Simulated audit write failure.');
        });
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->shopOwnerUser)
                ->putJson("/api/branches/{$this->branch1->id}", ['head_staff_id' => $this->staff1->id]);
            $this->fail('Expected the simulated audit write failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit write failure.', $exception->getMessage());
        } finally {
            Event::forget($event);
        }

        $this->assertNull($this->branch1->fresh()->head_staff_id);
        $this->assertSame($this->branch1->id, $this->staff1->fresh()->branch_id);
        $this->assertFalse($this->staff1User->fresh()->hasRole('Branch Head'));
        $this->assertDatabaseCount('branch_head_assignment_histories', 0);
    }

    public function test_login_and_user_endpoint_share_verified_branch_context()
    {
        $this->branch1->update(['head_staff_id' => $this->staff1->id]);
        $this->staff1User->assignRole($this->branchHeadRole);

        $login = $this->postJson('/api/login', [
            'email' => $this->staff1User->email,
            'password' => 'password',
        ])->assertOk();

        $currentUser = $this->withToken($login->json('token'))->getJson('/api/user')->assertOk();

        $this->assertTrue($login->json('user.is_branch_head'));
        $this->assertSame(['id' => $this->branch1->id, 'name' => $this->branch1->name], $login->json('user.branch'));
        $this->assertSame($login->json('user.branch'), $currentUser->json('branch'));
        $this->assertSame($login->json('user.permissions'), $currentUser->json('permissions'));
    }

    public function test_stale_branch_head_role_does_not_return_branch_context()
    {
        $this->staff1User->assignRole($this->branchHeadRole);
        $permission = Permission::create(['name' => 'branches.view', 'guard_name' => 'web']);
        $this->branchHeadRole->givePermissionTo($permission);

        $this->postJson('/api/login', [
            'email' => $this->staff1User->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.is_branch_head', false)
            ->assertJsonPath('user.branch', null);

        $this->actingAs($this->staff1User)->getJson('/api/branches')->assertForbidden();
    }

    public function test_inactive_staff_cannot_be_assigned()
    {
        $this->staff1->update(['status' => 'inactive']);

        $response = $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff1->id,
            ]);

        $response->assertStatus(422);
        $this->assertNull($this->branch1->fresh()->head_staff_id);
    }

    public function test_cross_shop_staff_assignment_fails()
    {
        // Create another shop with a user
        $otherShopUser = User::create([
            'name' => 'Other Shop Owner',
            'username' => 'other_shop_owner',
            'email' => 'otherowner@other.com',
            'role' => 'shop_owner',
            'password' => Hash::make('password'),
        ]);

        $otherShopOwner = ShopOwner::create([
            'user_id' => $otherShopUser->id,
            'shop_name' => 'Other Shop',
            'location' => 'Other Location',
        ]);

        $otherStaff = Staff::create([
            'user_id' => User::create([
                'name' => 'Other Staff',
                'username' => 'other_staff',
                'email' => 'other@other.com',
                'role' => 'staff',
                'password' => Hash::make('password'),
            ])->id,
            'shop_owner_id' => $otherShopOwner->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $otherStaff->id,
            ]);

        // Validation rejects cross-shop staff (422) or backend rejects (403)
        // Both are acceptable security outcomes
        $this->assertTrue(in_array($response->status(), [403, 422]));
    }

    public function test_branch_head_cannot_create_branches()
    {
        $this->staff1User->assignRole($this->branchHeadRole);
        $this->branch1->update(['head_staff_id' => $this->staff1->id]);

        $response = $this->actingAs($this->staff1User)
            ->postJson('/api/branches', [
                'name' => 'New Branch',
            ]);

        $response->assertStatus(403);
    }

    public function test_branch_head_cannot_delete_branches()
    {
        $this->staff1User->assignRole($this->branchHeadRole);
        $this->branch1->update(['head_staff_id' => $this->staff1->id]);

        $response = $this->actingAs($this->staff1User)
            ->deleteJson("/api/branches/{$this->branch1->id}");

        $response->assertStatus(403);
    }

    public function test_is_branch_head_consistency()
    {
        // Without assignment
        $this->assertFalse($this->staff1->isBranchHead());
        $this->assertFalse($this->staff1User->isBranchHead());

        // With role but no head_staff_id (inconsistent state)
        $this->staff1User->assignRole($this->branchHeadRole);
        $this->assertFalse($this->staff1->isBranchHead()); // Should return false without both checks
        $this->assertFalse($this->staff1User->isBranchHead());

        // With proper assignment
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff1->id,
            ]);

        $this->assertTrue($this->staff1->fresh()->isBranchHead());
        $this->assertTrue($this->staff1User->fresh()->isBranchHead());
    }
}
