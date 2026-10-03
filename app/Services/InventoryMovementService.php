<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Events\ProductStockAdjusted;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InventoryMovementService
{
    public function __construct(
        private UnitConversionService $conversion,
        private ProductStockService $stock,
    ) {}

    /** Directional movement. $quantity is a positive magnitude; the type decides the sign. */
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
        ?ProductVariant $variant = null,
    ): InventoryMovement {
        if ($type === MovementType::Adjustment) {
            throw new InvalidArgumentException('Use adjust() for adjustment movements.');
        }

        return DB::transaction(function () use (
            $product, $productUnit, $type, $quantity, $referenceType, $referenceId,
            $createdBy, $note, $allowNegativeStock, $variant
        ) {
            $locked = $this->lock($product, $productUnit, $variant);

            $magnitude  = $this->conversion->toBaseQuantity($productUnit, $this->dec($quantity));
            $signedBase = $type->increasesStock() ? $magnitude : bcmul($magnitude, '-1', 4);

            $this->assertStockAllows($locked, $variant, $signedBase, $allowNegativeStock);

            $movement = InventoryMovement::create([
                'product_id'     => $locked->id,
                'variant_id'     => $variant?->id,
                'unit_id'        => $productUnit->id,
                'quantity'       => $this->dec($quantity),
                'base_quantity'  => $signedBase,
                'movement_type'  => $type->value,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'note'           => $note,
                'created_by'     => $createdBy,
            ]);

            $this->saveStock($locked, $signedBase);

            return $movement;
        });
    }

    /** Freeform correction. $signedQuantity: positive = found stock, negative = shrinkage. */
    public function adjust(
        Product $product,
        ProductUnit $productUnit,
        string|float $signedQuantity,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $createdBy = null,
        ?string $note = null,
        ?ProductVariant $variant = null,
        bool $allowNegativeStock = false,
    ): InventoryMovement {
        return DB::transaction(function () use (
            $product, $productUnit, $signedQuantity, $referenceType, $referenceId,
            $createdBy, $note, $variant, $allowNegativeStock
        ) {
            $locked = $this->lock($product, $productUnit, $variant);

            $qty = $this->dec($signedQuantity);
            if (bccomp($qty, '0', 4) === 0) {
                throw new InvalidArgumentException('Adjustment quantity cannot be zero.');
            }

            $negative   = bccomp($qty, '0', 4) < 0;
            $magnitude  = $this->conversion->toBaseQuantity($productUnit, ltrim($qty, '-'));
            $signedBase = $negative ? bcmul($magnitude, '-1', 4) : $magnitude;

            $this->assertStockAllows($locked, $variant, $signedBase, $allowNegativeStock);

            $movement = InventoryMovement::create([
                'product_id'     => $locked->id,
                'variant_id'     => $variant?->id,
                'unit_id'        => $productUnit->id,
                'quantity'       => $qty,
                'base_quantity'  => $signedBase,
                'movement_type'  => MovementType::Adjustment->value,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'note'           => $note,
                'created_by'     => $createdBy,
            ]);

            $this->saveStock($locked, $signedBase);

            // listeners must only see committed stock
            DB::afterCommit(fn () => event(new ProductStockAdjusted($locked, $movement)));

            return $movement;
        });
    }

    /* ---- convenience wrappers ---- */

    public function purchase(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null, ?ProductVariant $variant = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::Purchase, $qty, $referenceType, $referenceId, $createdBy, allowNegativeStock: true, variant: $variant);
    }

    public function sale(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null, bool $allowNegativeStock = false, ?ProductVariant $variant = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::Sale, $qty, $referenceType, $referenceId, $createdBy, allowNegativeStock: $allowNegativeStock, variant: $variant);
    }

    public function saleReturn(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null, ?ProductVariant $variant = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::SaleReturn, $qty, $referenceType, $referenceId, $createdBy, allowNegativeStock: true, variant: $variant);
    }

    public function purchaseReturn(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null, ?ProductVariant $variant = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::PurchaseReturn, $qty, $referenceType, $referenceId, $createdBy, variant: $variant);
    }

    public function damage(Product $p, ProductUnit $u, string|float $qty, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null, ?string $note = null, ?ProductVariant $variant = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::Damage, $qty, $referenceType, $referenceId, $createdBy, $note, variant: $variant);
    }

    public function openingStock(Product $p, ProductUnit $u, string|float $qty, ?int $createdBy = null, ?ProductVariant $variant = null): InventoryMovement
    {
        return $this->record($p, $u, MovementType::OpeningStock, $qty, null, null, $createdBy, allowNegativeStock: true, variant: $variant);
    }

    /* ---- internals ---- */

    /** Row lock serialises every movement of one product, so two sales can't both take the last unit. */
    private function lock(Product $product, ProductUnit $unit, ?ProductVariant $variant): Product
    {
        $locked = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();

        if ((int) $unit->product_id !== (int) $locked->id) {
            throw new InvalidArgumentException("Unit #{$unit->id} does not belong to product #{$locked->id}.");
        }
        if ($variant && (int) $variant->product_id !== (int) $locked->id) {
            throw new InvalidArgumentException("Variant #{$variant->id} does not belong to product #{$locked->id}.");
        }
        if ($locked->stock_mode === 'from_components') {
            throw new InvalidArgumentException("\"{$locked->name}\" is a bundle: its stock comes from its components.");
        }
        if ($variant && $locked->stock_mode !== 'from_variants') {
            throw ValidationException::withMessages(['variant_id' => ["\"{$locked->name}\" does not use variants."]]);
        }
        if (! $variant && $locked->stock_mode === 'from_variants' && config('stock.require_variant', false)) {
            throw ValidationException::withMessages(['variant_id' => ['Choose which variant.']]);
        }

        return $locked;
    }

    private function assertStockAllows(Product $p, ?ProductVariant $variant, string $signedBase, bool $allow): void
    {
        if ($allow || ! $p->track_stock || $p->allow_negative_stock || bccomp($signedBase, '0', 4) >= 0) {
            return;
        }

        $have = $variant
            ? $this->stock->variantStock($variant->id)
            : $this->dec($p->getRawOriginal('current_stock'));

        if (bccomp(bcadd($have, $signedBase, 4), '0', 4) < 0) {
            $label = $variant ? "\"{$p->name}\" ({$variant->display_label})" : "\"{$p->name}\"";
            throw new InsufficientStockException(
                "Not enough stock for {$label}: " . $this->fmt($have) . ' in stock, ' . $this->fmt(ltrim($signedBase, '-')) . ' needed.'
            );
        }
    }

    /** Raw update: no timestamps touched, no mass-assignment, always the locked value. */
    private function saveStock(Product $p, string $signedBase): void
    {
        DB::table('products')->where('id', $p->id)->update([
            'current_stock' => bcadd($this->dec($p->getRawOriginal('current_stock')), $signedBase, 4),
        ]);
    }

    /** bcmath only accepts plain decimals; reject things like "1e3" that Laravel's `numeric` rule lets through. */
    private function dec(mixed $v): string
    {
        if ($v === null || $v === '') return '0.0000';
        if (is_string($v)) {
            if (! preg_match('/^-?\d+(\.\d+)?$/', $v)) {
                throw new InvalidArgumentException("Not a plain decimal number: {$v}");
            }
            return $v;
        }
        return number_format((float) $v, 4, '.', '');
    }

    private function fmt(string $n): string
    {
        return str_contains($n, '.') ? rtrim(rtrim($n, '0'), '.') : $n;
    }

    /**
     * Moves unassigned (product-level) stock onto a specific variant.
     * The product total is unchanged — two ledger rows cancel at the product level.
     */
    public function assignUnassignedStock(Product $product, ProductVariant $variant, string $quantity, ?int $userId = null): void
    {
        DB::transaction(function () use ($product, $variant, $quantity, $userId) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($locked->stock_mode !== 'from_variants' || (int) $variant->product_id !== (int) $locked->id) {
                throw ValidationException::withMessages(['variant_id' => ['That variant does not belong to this product.']]);
            }

            $qty = $this->dec($quantity);
            if (bccomp($qty, '0', 4) <= 0 || bccomp($qty, $this->stock->unassignedFor($locked), 4) > 0) {
                throw ValidationException::withMessages(['quantity' => ['Not that much unassigned stock.']]);
            }

            $base = $locked->units()->where('is_base', true)->firstOrFail();

            // Subtract from product-level (no variant) then add to variant — product total unchanged.
            foreach ([[null, bcmul($qty, '-1', 4)], [$variant->id, $qty]] as [$variantId, $signed]) {
                InventoryMovement::create([
                    'product_id'     => $locked->id,
                    'variant_id'     => $variantId,
                    'unit_id'        => $base->id,
                    'quantity'       => $signed,
                    'base_quantity'  => $signed,
                    'movement_type'  => MovementType::Adjustment->value,
                    'reference_type' => null,
                    'reference_id'   => null,
                    'note'           => 'Moved unassigned stock to a variant',
                    'created_by'     => $userId,
                ]);
            }
        });
    }
}
