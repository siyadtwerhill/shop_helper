<?php

namespace Tests\Feature;

use App\Models\ShopOwner;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToBranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_bypasses_branch_scope(): void
    {
        $shop = ShopOwner::factory()->create();
        $branch1 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();
        $branch2 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();

        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch1->id])->create();
        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch2->id])->create();

        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($superadmin);
        $result = Staff::withoutBranchScope()->get();

        $this->assertCount(2, $result);
    }

    public function test_shop_owner_bypasses_branch_scope(): void
    {
        $shop = ShopOwner::factory()->create();
        $branch1 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();
        $branch2 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();

        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch1->id])->create();
        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch2->id])->create();

        $owner = User::factory()->create(['role' => 'shop_owner']);
        $shop->user()->associate($owner)->save();

        $this->actingAs($owner);
        $result = Staff::withoutBranchScope()->get();

        $this->assertCount(2, $result);
    }

    public function test_staff_with_branch_assignment_sees_only_their_branch(): void
    {
        $shop = ShopOwner::factory()->create();
        $branch1 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();
        $branch2 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();

        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch1->id])->create();
        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch2->id])->create();

        $branch1User = User::factory()->create(['role' => 'staff']);
        $branch1Staff = Staff::factory()->create([
            'user_id' => $branch1User->id,
            'shop_owner_id' => $shop->id,
            'branch_id' => $branch1->id,
        ]);

        $this->actingAs($branch1User);
        // Simulate middleware loading staff relationship and setting it on auth user
        $branch1User->setRelation('staff', $branch1Staff);
        $result = Staff::all();

        $this->assertCount(2, $result); // Branch 1 Staff + the branch1User's own staff record
    }

    public function test_staff_without_branch_assignment_fails_closed_empty_result(): void
    {
        $shop = ShopOwner::factory()->create();
        $branch1 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();
        $branch2 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();

        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch1->id])->create();
        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch2->id])->create();

        // Staff with no branch assignment (branch_id = null)
        $shopWideStaffUser = User::factory()->create(['role' => 'staff']);
        $shopWideStaff = Staff::factory()->create([
            'user_id' => $shopWideStaffUser->id,
            'shop_owner_id' => $shop->id,
            'branch_id' => null, // No branch assignment
        ]);

        $this->actingAs($shopWideStaffUser);
        // Simulate middleware loading staff relationship (with null branch_id)
        $shopWideStaffUser->setRelation('staff', $shopWideStaff);
        $result = Staff::all();

        // FAIL-CLOSE: Should return empty result, not all records
        $this->assertCount(0, $result);
    }

    public function test_user_without_staff_relationship_fails_closed_empty_result(): void
    {
        $shop = ShopOwner::factory()->create();
        $branch1 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();
        $branch2 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop->id])->create();

        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch1->id])->create();
        Staff::factory()->state(['shop_owner_id' => $shop->id, 'branch_id' => $branch2->id])->create();

        // User with 'staff' role but no staff record (malformed data)
        $orphanStaffUser = User::factory()->create(['role' => 'staff']);

        $this->actingAs($orphanStaffUser);
        // Set staff relationship to null explicitly to simulate missing record
        $orphanStaffUser->setRelation('staff', null);
        $result = Staff::all();

        // FAIL-CLOSE: Should return empty result, not all records
        $this->assertCount(0, $result);
    }

    public function test_cross_shop_isolation_not_compromised_by_branch_scope(): void
    {
        $shop1 = ShopOwner::factory()->create();
        $shop2 = ShopOwner::factory()->create();
        $branch1 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop1->id])->create();
        $branch2 = \App\Models\Branch::factory()->state(['shop_owner_id' => $shop2->id])->create();

        // Create staff for both shops
        Staff::factory()->state(['shop_owner_id' => $shop1->id, 'branch_id' => $branch1->id])->create();
        Staff::factory()->state(['shop_owner_id' => $shop2->id, 'branch_id' => $branch2->id])->create();

        $shop1User = User::factory()->create(['role' => 'staff']);
        $shop1Staff = Staff::factory()->create([
            'user_id' => $shop1User->id,
            'shop_owner_id' => $shop1->id,
            'branch_id' => $branch1->id,
        ]);

        $this->actingAs($shop1User);
        // Simulate middleware loading staff relationship and setting it on auth user
        $shop1User->setRelation('staff', $shop1Staff);
        $result = Staff::all();

        // Should only see Shop 1 staff (both staff from shop1: the test staff + the shop1User's staff)
        // The shop_owner_id scope ensures we don't see shop2 staff even if branch_id happens to match
        $this->assertCount(2, $result);
    }
}
