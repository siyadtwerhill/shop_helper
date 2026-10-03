<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleItemService
{
    public function __construct(
        private InventoryMovementService $movements,
        private UnitConversionService $conversion,
        private BundleSaleService $bundleSales,
    ) {
    }

    /**
     * Sell $quantity of $product in $productUnit's unit. Creates the sale_items row AND
     * the matching 'sale' inventory movement in one transaction.
     */
    public function sell(
        Product $product,
        ProductUnit $productUnit,
        string|float $quantity,
        string $unitPrice,
        int $saleId,
        ?string $originalUnitPrice = null,
        string $discountAmount = '0.00',
        ?int $createdBy = null,
        bool $allowNegativeStock = false,
        ?ProductVariant $variant = null,
    ): SaleItem {
        if (! $productUnit->is_sellable) {
            throw new InvalidArgumentException(
                "Unit #{$productUnit->id} is not marked sellable for product #{$product->id}."
            );
        }

        return DB::transaction(function () use ($product, $productUnit, $quantity, $unitPrice, $saleId, $originalUnitPrice, $discountAmount, $createdBy, $allowNegativeStock, $variant) {
            $baseQuantity = $this->conversion->toBaseQuantity($productUnit, $quantity);

            $saleItem = SaleItem::create([
                'sale_id'             => $saleId,
                'product_id'          => $product->id,
                'variant_id'          => $variant?->id,
                'unit_id'             => $productUnit->id,
                'quantity'            => (string) $quantity,
                'base_quantity'       => $baseQuantity,
                'unit_price'          => $unitPrice,
                'original_unit_price' => $originalUnitPrice ?? ($variant?->selling_price ?? $productUnit->selling_price),
                'discount_amount'     => $discountAmount,
            ]);

            $this->movements->sale(
                $product,
                $productUnit,
                $quantity,
                referenceType: SaleItem::class,
                referenceId: $saleItem->id,
                createdBy: $createdBy,
                allowNegativeStock: $allowNegativeStock,
                variant: $variant,
            );

            return $saleItem;
        });
    }

    /**
     * Sell a bundle product: creates the SaleItem for the bundle itself,
     * then deducts stock only from its components (the bundle has no ledger rows).
     */
    public function sellBundle(
        Product $bundleProduct,
        ProductBundle $bundle,
        ProductUnit $unit,
        string|float $quantity,
        string $unitPrice,
        int $saleId,
        ?int $createdBy = null,
    ): SaleItem {
        if (! $unit->is_sellable) {
            throw new InvalidArgumentException("Unit #{$unit->id} is not sellable for product #{$bundleProduct->id}.");
        }

        return DB::transaction(function () use ($bundleProduct, $bundle, $unit, $quantity, $unitPrice, $saleId, $createdBy) {
            $baseQuantity = $this->conversion->toBaseQuantity($unit, $quantity);

            $item = SaleItem::create([
                'sale_id'             => $saleId,
                'product_id'          => $bundleProduct->id,
                'unit_id'             => $unit->id,
                'quantity'            => (string) $quantity,
                'base_quantity'       => $baseQuantity,
                'unit_price'          => $unitPrice,
                'original_unit_price' => (string) $bundle->bundle_price,
                'discount_amount'     => '0.00',
            ]);

            // The bundle itself never gets a movement; only its components do.
            $this->bundleSales->deductComponents(
                $bundle,
                $baseQuantity,
                $createdBy,
                SaleItem::class,
                $item->id,
            );

            return $item;
        });
    }
}
