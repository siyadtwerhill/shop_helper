<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['shop_owner_id', 'name', 'address', 'phone', 'head_staff_id', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function shopOwner(): BelongsTo
    {
        return $this->belongsTo(ShopOwner::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'head_staff_id');
    }

    public function employeeCount(): int
    {
        return $this->staff()->count();
    }
}
