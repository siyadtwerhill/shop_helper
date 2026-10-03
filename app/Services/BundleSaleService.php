<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\ProductBundle;
use App\Models\ProductVariant;

class BundleSaleService
{
    public function __construct(private InventoryMovementService $movements)
    {
    }

    /**
     * Deduct stock for every component when $bundleQuantity bundles are sold.
     * Components are processed in component_product_id order so two concurrent
     * sales always lock rows in the same sequence and cannot deadlock.
     */
    public function deductComponents(
        ProductBundle $bundle,
        string|float $bundleQuantity,
        ?int $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        bool $allowNegativeStock = false,
    ): array {
        $movements = [];

        $items = $bundle->items()->with(['component', 'unit'])->orderBy('component_product_id')->get();

        foreach ($items as $item) {
            if (! $item->component) {
                throw new \InvalidArgumentException('A component of this bundle has been archived.');
            }

            $variantId = $item->getAttribute('component_variant_id'); // column arrives in P5
            $variant = $variantId ? ProductVariant::find($variantId) : null;

            $movements[] = $this->movements->record(
                $item->component,
                $item->unit,
                MovementType::Sale,
                bcmul((string) $bundleQuantity, (string) $item->quantity, 4),
                referenceType: $referenceType ?? ProductBundle::class,
                referenceId: $referenceId ?? $bundle->id,
                createdBy: $createdBy,
                allowNegativeStock: $allowNegativeStock,
                variant: $variant,
            );
        }

        return $movements;
    }
}
