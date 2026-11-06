<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'branch_id',
        'sale_id',
        'customer_id',
        'return_number',
        'status',
        'reason',
        'notes',
        'total_refund_amount',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'total_refund_amount' => 'decimal:2',
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

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'product_return_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'product_return_id');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('created_at', $date);
    }
}
