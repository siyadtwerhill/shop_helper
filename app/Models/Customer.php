<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_owner_id',
        'name',
        'email',
        'phone',
        'address',
        'status',
        'total_purchases',
    ];

    protected $casts = [
        'total_purchases' => 'decimal:2',
    ];

    public function shopOwner()
    {
        return $this->belongsTo(ShopOwner::class);
    }
}
