<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'name',
        'symbol',
        'type',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function productUnits()
    {
        return $this->hasMany(ProductUnit::class);
    }

    /** System units (KG, G, Liter, Piece, ...) visible to every tenant. */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    /** Units usable by a given tenant: system units + that tenant's own custom units. */
    public function scopeAvailableTo(Builder $query, int $shopOwnerId): Builder
    {
        return $query->where(function (Builder $q) use ($shopOwnerId) {
            $q->where('is_system', true)
              ->orWhere('shop_owner_id', $shopOwnerId);
        });
    }
}
