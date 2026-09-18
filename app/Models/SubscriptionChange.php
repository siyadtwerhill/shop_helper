<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionChange extends Model
{
    protected $fillable = ['subscription_id', 'from_plan_id', 'to_plan_id', 'changed_by_user_id', 'reason'];

    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function fromPlan(): BelongsTo { return $this->belongsTo(Plan::class, 'from_plan_id'); }
    public function toPlan(): BelongsTo { return $this->belongsTo(Plan::class, 'to_plan_id'); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by_user_id'); }
}
