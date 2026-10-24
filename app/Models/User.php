<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = ['name', 'username', 'email', 'password', 'role', 'profile_photo', 'phone'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isShopOwner(): bool
    {
        return $this->role === 'shop_owner';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    // A branch_head is still role = 'staff' at the User level — this
    // checks both the Spatie role assignment and the branch.head_staff_id
    // for consistency. "branch_head" is a role within a shop's team, not a
    // top-level account type.
    public function isBranchHead(): bool
    {
        if (!$this->isStaff()) {
            return false;
        }

        // Check Spatie role first (with team context)
        $hasRole = $this->hasRole('Branch Head');

        // Also verify branch.head_staff_id matches this staff record
        $matchesBranchHead = $this->staff && $this->staff->branch && $this->staff->branch->head_staff_id === $this->staff->id;

        // Both should be true for consistency
        return $hasRole && $matchesBranchHead;
    }

    public function shopOwner()
    {
        return $this->hasOne(ShopOwner::class);
    }

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }
}
