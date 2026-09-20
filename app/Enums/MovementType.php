<?php

namespace App\Enums;

enum MovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';
    case Adjustment = 'adjustment';
    case Damage = 'damage';
    case OpeningStock = 'opening_stock';

    /**
     * Whether this type inherently increases stock. Adjustment has no fixed direction —
     * its sign comes from the caller — so it deliberately throws if asked here.
     */
    public function increasesStock(): bool
    {
        return match ($this) {
            self::Purchase, self::SaleReturn, self::OpeningStock => true,
            self::Sale, self::PurchaseReturn, self::Damage => false,
            self::Adjustment => throw new \LogicException(
                'Adjustment direction is explicit (signed quantity), not derived from the type.'
            ),
        };
    }
}