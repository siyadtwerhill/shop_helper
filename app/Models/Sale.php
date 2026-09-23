<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'subtotal',
        'total_amount',
        'total_discount',
        'payment_method',
        'status',
        'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'total_discount' => 'decimal:2',
    ];

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}