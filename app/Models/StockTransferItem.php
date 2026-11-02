<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    protected $fillable = ['stock_transfer_id', 'product_id', 'variant_id', 'product_unit_id', 'quantity'];

    protected function casts(): array { return ['quantity' => 'decimal:4']; }
    public function transfer(): BelongsTo { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function productUnit(): BelongsTo { return $this->belongsTo(ProductUnit::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
}
