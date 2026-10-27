<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'branch_id',
        'shop_owner_id',
        'current_stock',
        'reorder_level',
    ];

    protected $casts = [
        'current_stock' => 'decimal:4',
        'reorder_level' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class);
    }

    /**
     * Check if stock is below reorder level.
     */
    public function isBelowReorderLevel(): bool
    {
        return $this->reorder_level !== null && $this->current_stock < $this->reorder_level;
    }

    /**
     * Scope to a specific branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope to a specific shop.
     */
    public function scopeForShop($query, $shopOwnerId)
    {
        return $query->where('shop_owner_id', $shopOwnerId);
    }

    /**
     * Scope to products below reorder level.
     */
    public function scopeBelowReorderLevel($query)
    {
        return $query->whereColumn('current_stock', '<', 'reorder_level')
            ->whereNotNull('reorder_level');
    }
}
