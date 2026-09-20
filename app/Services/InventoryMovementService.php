<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryMovementService
{
    public function __construct(private UnitConversionService $conversion)
    {
    }

    /**
     * Record a directional movement (purchase, sale, sale_return, purchase_return, damage,
     * opening_stock). Direction is derived from the type — quantity is always a positive
     * magnitude here. Use adjust() for signed, freeform corrections.
     */
    public function record(
        Product $product,
        ProductUnit $productUnit,
        MovementType $type,
        string|float $quantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null,
        ?string $note = null,
        bool $allowNegativeStock = false,
    ): InventoryMovement {
        if ($type === MovementType::Adjustment) {
            throw new InvalidArgumentException('Use adjust() for adjustment movements.');
        }

        return DB::transaction(function () use ($product, $productUnit, $type, $quantity, $referenceType, $referenceId, $createdBy, $note, $allowNegativeStock) {
            $magnitude = $this->conversion->toBaseQuantity($productUnit, $quantity);
            $signedBase = $type->increasesStock() ? $magnitude : bcmul($magnitude, '-1', 4);
            $resultingStock = bcadd((string) $product->current_stock, $signedBase, 4);

            if (! $allowNegativeStock && bccomp($resultingStock, '0', 4) < 0) {
                throw new InsufficientStockException(
                    "Recording this {$type->value} would take product #{$product->id} below zero stock ({$resultingStock})."
                );
            }

            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'unit_id' => $productUnit->id,
                'quantity' => (string) $quantity,
                'base_quantity' => $signedBase,
                'movement_type' => $type->value,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
                'created_by' => $createdBy,
            ]);

            $product->forceFill(['current_stock' => $resultingStock])->save();

            return $movement;
        });
    }

    /** Freeform correction — $signedQuantity can be positive (found extra stock) or negative (shrinkage). */
    public function adjust(
        Product $product,
        ProductUnit $productUnit,
        string|float $signedQuantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null,
        ?string $note = null,
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $productUnit, $signedQuantity, $referenceType, $referenceId, $createdBy, $note) {
            $isNegative = (float) $signedQuantity < 0;
            $magnitude = $this->conversion->toBaseQuantity($productUnit, abs((float) $signedQuantity));
            $signedBase = $isNegative ? bcmul($magnitude, '-1', 4) : $magnitude;
            $resultingStock = bcadd((string) $product->current_stock, $signedBase, 4);

            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'unit_id' => $productUnit->id,
                'quantity' => (string) $signedQuantity,
                'base_quantity' => $signedBase,
                'movement_type' => MovementType::Adjustment->value,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
                'created_by' => $createdBy,
            ]);

            $product->forceFill(['current_stock' => $resultingStock])->save();

            return $movement;
        });
    }

    // Convenience wrappers — thin, but keep call sites in controllers/services readable.

    public function purchase(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::Purchase, $qty, $referenceType, $referenceId, $createdBy, allowNegativeStock: true);
    }

    public function sale(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::Sale, $qty, $referenceType, $referenceId, $createdBy);
    }

    public function saleReturn(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::SaleReturn, $qty, $referenceType, $referenceId, $createdBy, allowNegativeStock: true);
    }

    public function purchaseReturn(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::PurchaseReturn, $qty, $referenceType, $referenceId, $createdBy);
    }

    public function damage(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null, ?string $note = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::Damage, $qty, $referenceType, $referenceId, $createdBy, $note);
    }

    public function openingStock(Product $p, ProductUnit $u, string|float $qty, ?int $createdBy = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::OpeningStock, $qty, null, null, $createdBy, allowNegativeStock: true);
    }
}