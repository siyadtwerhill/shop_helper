<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, BelongsToBranch;

    protected $fillable = [
        'user_id',
        'shop_owner_id',
        'branch_id',
        'position',
        'phone',
        'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['position' => 'string', 'status' => 'string'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function isBranchHead(): bool
    {
        return $this->branch && $this->branch->head_staff_id === $this->id;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
