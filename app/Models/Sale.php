<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'total_amount',
        'total_discount',
        'payment_method',
        'status',
    ];

    protected $casts = [
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
}