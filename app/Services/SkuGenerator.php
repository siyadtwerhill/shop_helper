<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class SkuGenerator
{
    /**
     * Format: {CATEGORY_PREFIX}-{5-digit sequential number}
     * e.g. "BEV-00001". Falls back to "GEN" if no category.
     */
    public function generate(int $shopOwnerId, ?string $categoryName = null): string
    {
        $prefix = $categoryName
            ? Str::upper(Str::limit(preg_replace('/[^A-Za-z]/', '', $categoryName), 3, ''))
            : 'GEN';

        $prefix = $prefix ?: 'GEN';

        do {
            $count = Product::where('shop_owner_id', $shopOwnerId)
                ->where('sku', 'like', "{$prefix}-%")
                ->count();

            $candidate = sprintf('%s-%05d', $prefix, $count + 1);
        } while (Product::where('sku', $candidate)->exists()); // guards a rare race

        return $candidate;
    }
}