<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = [
        'shop_owner_id', 'plan_id', 'amount', 'method',
        'reference_code', 'proof_path', 'status',
        'verified_by_user_id', 'verified_at', 'rejection_reason', 'expires_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = ['proof_url'];

    public function shopOwner(): BelongsTo
    {
        return $this->belongsTo(ShopOwner::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function getProofUrlAttribute(): ?string
    {
        return $this->proof_path ? asset('storage/' . $this->proof_path) : null;
    }

    public static function generateReferenceCode(): string
    {
        do {
            $code = 'SP-' . strtoupper(Str::random(5));
        } while (static::where('reference_code', $code)->exists());

        return $code;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_verification';
    }
}
