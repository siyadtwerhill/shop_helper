<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmployeeInvitation extends Model
{
    use HasFactory;

    protected $fillable = ['shop_owner_id', 'name', 'email', 'phone', 'role_id', 'branch_id', 'token', 'expires_at'];
    protected $casts = ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public static function createFor(ShopOwner $shop, array $data): self
    {
        return static::create([
            'shop_owner_id' => $shop->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role_id' => $data['role_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'token' => Str::random(48),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
