<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'variant_id', 'product_unit_id', 'quantity', 'unit_cost', 'line_total'];

    protected function casts(): array { return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function productUnit(): BelongsTo { return $this->belongsTo(ProductUnit::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class); }
}
