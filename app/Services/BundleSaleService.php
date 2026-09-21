<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\ProductBundle;

class BundleSaleService
{
    public function __construct(private InventoryMovementService $movements)
    {
    }

    /**
     * Deduct stock for every component when $bundleQuantity bundles are sold (spec §11:
     * "Selling one bundle should automatically reduce the inventory of its component products").
     * The bundle "product" itself is never inventory-tracked — only its components are.
     */
    public function deductComponents(
        ProductBundle $bundle,
        string|float $bundleQuantity,
        ?int $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): array {
        $movements = [];

        foreach ($bundle->items as $item) {
            $requiredQty = bcmul((string) $bundleQuantity, (string) $item->quantity, 4);

            $movements[] = $this->movements->record(
                $item->component,
                $item->unit,
                MovementType::Sale,
                $requiredQty,
                referenceType: $referenceType ?? ProductBundle::class,
                referenceId: $referenceId ?? $bundle->id,
                createdBy: $createdBy,
            );
        }

        return $movements;
    }
}
