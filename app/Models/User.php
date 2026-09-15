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

    protected $fillable = ['name', 'username', 'email', 'password', 'role'];
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
    // checks the Spatie role assignment, not the `role` column, since
    // "branch_head" is a role within a shop's team, not a top-level
    // account type.
    public function isBranchHead(): bool
    {
        return $this->isStaff() && $this->hasRole('branch_head');
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
