<?php

namespace App\Models\Concerns;

use App\Models\ProductPriceRule;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Add to Product.php: use App\Models\Concerns\HasPriceRules; */
trait HasPriceRules
{
    public function priceRules(): HasMany
    {
        return $this->hasMany(ProductPriceRule::class);
    }
}