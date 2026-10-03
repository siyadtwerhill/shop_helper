<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\BlueprintFactory;

class Blueprint extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return BlueprintFactory::new();
    }

    protected $fillable = [
        'shop_owner_id',
        'name',
        'description',
        'preset_key',
        'is_default',
        'status',
        'capabilities',
        'pricing_policy',
        'unit_policy',
        'default_base_unit_id',
        'allowed_unit_ids',
        'layout',
        'version',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'capabilities' => 'array',
        'pricing_policy' => 'array',
        'unit_policy' => 'array',
        'allowed_unit_ids' => 'array',
        'layout' => 'array',
    ];

    public function shopOwner(): BelongsTo
    {
        return $this->belongsTo(ShopOwner::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(BlueprintField::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
