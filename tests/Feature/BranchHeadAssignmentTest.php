<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchHeadAssignmentHistory;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Models\ShopOwner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_assignment_preserves_existing_roles()
    {
        // Give staff1 Cashier role first
        $this->staff1User->assignRole($this->cashierRole);
        $this->assertTrue($this->staff1User->hasRole('Cashier'));

        // Assign as branch head
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff1->id,
            ]);

        // Should still have Cashier role
        $this->assertTrue($this->staff1User->fresh()->hasRole('Cashier'));
        // And also have Branch Head role
        $this->assertTrue($this->staff1User->fresh()->hasRole('Branch Head'));
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

        // Replace with staff2
        $this->actingAs($this->shopOwnerUser)
            ->putJson("/api/branches/{$this->branch1->id}", [
                'head_staff_id' => $this->staff2->id,
            ]);

        // Staff1 should lose Branch Head but keep Cashier
        $this->assertFalse($this->staff1User->fresh()->hasRole('Branch Head'));
        $this->assertTrue($this->staff1User->fresh()->hasRole('Cashier'));

        // Staff2 should have both roles
        $this->assertTrue($this->staff2User->fresh()->hasRole('Branch Head'));
        $this->assertTrue($this->staff2User->fresh()->hasRole('Cashier'));
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
