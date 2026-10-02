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
        'sku', 'qr_path', 'status', 'product_type', 'current_stock', 'base_unit_id',
        'pricing_mode', 'cost_price', 'min_margin_percent', 'min_price',
        'blueprint_id', 'blueprint_version', 'stock_mode', 'attributes',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'min_margin_percent' => 'decimal:2',
        'min_price' => 'decimal:2',
        'attributes' => 'array',
    ];

    protected $appends = ['qr_url', 'current_stock', 'is_bundle', 'available_stock'];

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
        return $this->barcodes()->where('is_primary', true)->first();
    }

    public function getQrUrlAttribute(): ?string
    {
        return $this->qr_path ? asset('storage/' . $this->qr_path) : null;
    }

    public function getCurrentStockAttribute(): string
    {
        // Sum all inventory movements to get current stock
        $sum = $this->inventoryMovements()
            ->selectRaw('COALESCE(SUM(base_quantity), 0) as total')
            ->value('total');
        
        return (string) $sum;
    }

    public function getIsBundleAttribute(): bool
    {
        return $this->bundle()->exists();
    }

    public function getAvailableStockAttribute(): string
    {
        // For bundles, calculate available stock based on component products
        if ($this->product_type === 'bundle' && $this->bundle) {
            $bundle = $this->bundle;
            $minStock = null;
            
            foreach ($bundle->items as $item) {
                $componentStock = (float) $item->component->current_stock;
                $requiredQty = (float) $item->quantity;
                $possibleBundles = $componentStock / $requiredQty;
                
                if ($minStock === null || $possibleBundles < $minStock) {
                    $minStock = $possibleBundles;
                }
            }
            
            return (string) floor($minStock ?? 0);
        }
        
        // For variant products, sum up all variant stocks (they have their own current_stock attribute)
        if ($this->product_type === 'variant' && $this->variants()->exists()) {
            $totalStock = 0;
            foreach ($this->variants as $variant) {
                $totalStock += (float) ($variant->current_stock ?? 0);
            }
            return (string) $totalStock;
        }
        
        // For neutral products, return current stock
        return $this->current_stock;
    }

}
