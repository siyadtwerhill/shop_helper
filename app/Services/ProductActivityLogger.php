<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductActivityLog;

class ProductActivityLogger
{
    public function log(
        Product $product,
        string $action,
        ?int $userId = null,
        ?string $field = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $description = null,
    ): ProductActivityLog {
        return ProductActivityLog::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'action' => $action,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
        ]);
    }
}
