<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBundle extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'bundle_price'];

    protected $casts = ['bundle_price' => 'decimal:2'];

    /** The Product row that represents this bundle as a sellable thing. */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function items()
    {
        return $this->hasMany(ProductBundleItem::class);
    }

    /** Sum of each component's own unit price × quantity — the "normal total" from spec §11. */
    public function normalTotal(): string
    {
        return $this->items->reduce(
            fn (string $carry, ProductBundleItem $item) => bcadd(
                $carry,
                bcmul((string) $item->quantity, (string) ($item->unit->selling_price ?? '0'), 2),
                2
            ),
            '0.00'
        );
    }
}
