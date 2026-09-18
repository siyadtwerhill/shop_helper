<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'shop_owner_id', 'plan_id', 'status',
        'trial_ends_at', 'current_period_end', 'cancelled_at', 'expired_at', 'pending_plan_id',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function shopOwner(): BelongsTo
    {
        return $this->belongsTo(ShopOwner::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(SubscriptionChange::class);
    }

    public function pendingPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'pending_plan_id');
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        return $this->current_period_end
            && $this->current_period_end->isFuture()
            && $this->current_period_end->diffInDays(now()) <= $days
            && !in_array($this->status, ['cancelled', 'expired']);
    }

    public function daysExpired(): ?int
    {
        return $this->expired_at?->diffInDays(now());
    }
}
