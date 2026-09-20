<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleItemService
{
    public function __construct(
        private InventoryMovementService $movements,
        private UnitConversionService $conversion,
    ) {
    }

    /**
     * Sell $quantity of $product in $productUnit's unit. Creates the sale_items row AND
     * the matching 'sale' inventory movement in one transaction, so they can never drift apart.
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
    ): SaleItem {
        if (! $productUnit->is_sellable) {
            throw new InvalidArgumentException(
                "Unit #{$productUnit->id} is not marked sellable for product #{$product->id}."
            );
        }

        return DB::transaction(function () use ($product, $productUnit, $quantity, $unitPrice, $saleId, $originalUnitPrice, $discountAmount, $createdBy) {
            $baseQuantity = $this->conversion->toBaseQuantity($productUnit, $quantity);

            $saleItem = SaleItem::create([
                'sale_id' => $saleId,
                'product_id' => $product->id,
                'unit_id' => $productUnit->id,
                'quantity' => (string) $quantity,
                'base_quantity' => $baseQuantity,
                'unit_price' => $unitPrice,
                'original_unit_price' => $originalUnitPrice ?? $productUnit->selling_price,
                'discount_amount' => $discountAmount,
            ]);

            $this->movements->sale(
                $product,
                $productUnit,
                $quantity,
                referenceType: SaleItem::class,
                referenceId: $saleItem->id,
                createdBy: $createdBy,
            );

            return $saleItem;
        });
    }
}