<?php

namespace App\Models\Concerns;

use App\Models\ProductVariant;

/** Add to Product.php: use App\Models\Concerns\HasVariants; */
trait HasVariants
{
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
