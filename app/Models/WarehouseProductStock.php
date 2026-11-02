<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseProductStock extends Model
{
    protected $fillable = ['shop_owner_id', 'warehouse_id', 'product_id', 'current_stock', 'reorder_level'];

    protected function casts(): array { return ['current_stock' => 'decimal:4', 'reorder_level' => 'decimal:4']; }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
