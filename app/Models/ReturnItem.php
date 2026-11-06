<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_return_id',
        'sale_item_id',
        'product_id',
        'variant_id',
        'quantity_returned',
        'unit_price',
        'refund_amount',
        'reason',
    ];

    protected $casts = [
        'quantity_returned' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function productReturn()
    {
        return $this->belongsTo(ProductReturn::class, 'product_return_id');
    }

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
