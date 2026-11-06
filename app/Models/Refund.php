<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'branch_id',
        'product_return_id',
        'refund_number',
        'refund_method',
        'refund_amount',
        'status',
        'reference_number',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function productReturn()
    {
        return $this->belongsTo(ProductReturn::class, 'product_return_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}
