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
use App\Models\Branch;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasUnits, HasInventoryMovements, HasPriceRules, HasVariants, HasBundle, HasActivityLogs;

    protected $fillable = [
        'shop_owner_id', 'branch_id', 'category_id', 'brand_id',
        'name', 'description', 'internal_notes', 'image_path',
        'sku', 'qr_path', 'status', 'product_type', 'base_unit_id',
        'pricing_mode', 'cost_price', 'min_margin_percent', 'min_price', 'min_stock',
        'blueprint_id', 'blueprint_version', 'stock_mode', 'custom_fields',
        'reorder_quantity', 'track_stock', 'allow_negative_stock', 'is_sellable',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'min_margin_percent' => 'decimal:2',
        'min_price' => 'decimal:2',
        'min_stock' => 'decimal:4',
        'current_stock' => 'decimal:4',
        'reorder_quantity' => 'decimal:4',
        'track_stock' => 'boolean',
        'allow_negative_stock' => 'boolean',
        'is_sellable' => 'boolean',
        'custom_fields' => 'array',
    ];

    protected $appends = ['qr_url', 'image_url'];

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
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function barcodes(): HasMany { return $this->hasMany(ProductBarcode::class); }
    public function blueprint(): BelongsTo { return $this->belongsTo(Blueprint::class); }

    public function primaryBarcode(): ?ProductBarcode
    {
        return $this->barcodes()->where('is_primary', true)->whereNull('product_variant_id')->first();
    }

    public function getQrUrlAttribute(): ?string
    {
        return $this->qr_path ? asset('storage/' . $this->qr_path) : null;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }
}
