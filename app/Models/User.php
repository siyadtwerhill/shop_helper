<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->account_code)) {
                do {
                    $code = 'SP-' . Str::upper(Str::random(12));
                } while (static::where('account_code', $code)->exists());

                $user->account_code = $code;
            }
        });
    }

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
        if (! $this->isStaff()) {
            return false;
        }

        $staff = $this->staff;
        $branch = $staff?->branch;

        if (! $staff || $staff->status !== 'active' || ! $branch || ! $branch->is_active
            || (int) $staff->shop_owner_id !== (int) $branch->shop_owner_id
            || (int) $staff->branch_id !== (int) $branch->id
            || (int) $branch->head_staff_id !== (int) $staff->id) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($staff->shop_owner_id);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $this->hasAnyRole(['Branch Head', 'branch_head']);
        } finally {
            $this->unsetRelation('roles')->unsetRelation('permissions');
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    /** Verified branch context for Branch Head clients; stale assignments return null. */
    public function branchHeadContext(): ?array
    {
        if (! $this->isBranchHead()) {
            return null;
        }

        return $this->staff->branch->only(['id', 'name']);
    }

    public function shopOwner()
    {
        return $this->hasOne(ShopOwner::class);
    }

    public function staff()
    {
        // Identity resolution must bypass Staff's row scope; the scope itself
        // reads this relation to establish the authenticated staff member's branch.
        return $this->hasOne(Staff::class)->withoutGlobalScope('branch');
    }
}
