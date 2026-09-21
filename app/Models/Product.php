<?php

namespace App\Models;

use App\Services\BarcodeGenerator;
use App\Services\SkuGenerator;
use App\Models\Concerns\HasUnits;
use App\Models\Concerns\HasInventoryMovements;
use App\Models\Concerns\HasPriceRules;
use App\Models\Concerns\HasVariants;
use App\Models\Concerns\HasBundle;
use App\Models\Concerns\HasActivityLogs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ShopOwner;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasUnits, HasInventoryMovements, HasPriceRules, HasVariants, HasBundle, HasActivityLogs;

    protected $fillable = [
        'shop_owner_id', 'category_id', 'brand_id',
        'name', 'description', 'internal_notes', 'image_path',
        'sku', 'qr_path', 'status', 'current_stock', 'base_unit_id',
        'pricing_mode', 'cost_price', 'min_margin_percent', 'min_price',
    ];

    protected $casts = [
        'current_stock' => 'decimal:4',
        'cost_price' => 'decimal:2',
        'min_margin_percent' => 'decimal:2',
        'min_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // SKU is generated here (needs to exist before insert, unique constraint).
        // Barcode + QR happen in the controller after the row has an ID —
        // barcode needs no ID dependency, but QR's stored filename does.
        static::creating(function (Product $product) {
            if (!$product->sku) {
                $product->sku = app(SkuGenerator::class)->generate(
                    $product->shop_owner_id,
                    $product->category?->name
                );
            }
        });
    }

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }
    public function shopOwner(): BelongsTo { return $this->belongsTo(ShopOwner::class); }
    public function barcodes(): HasMany { return $this->hasMany(ProductBarcode::class); }

    public function primaryBarcode(): ?ProductBarcode
    {
        return $this->barcodes()->where('is_primary', true)->first();
    }

    public function getQrUrlAttribute(): ?string
    {
        return $this->qr_path ? asset('storage/' . $this->qr_path) : null;
    }

    protected $appends = ['qr_url'];
}
