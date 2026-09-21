<?php

namespace App\Models\Concerns;

use App\Models\ProductBundle;
use App\Models\ProductBundleItem;

/** Add to Product.php: use App\Models\Concerns\HasBundle; */
trait HasBundle
{
    /** If this product IS a bundle, its definition (price + components). */
    public function bundle()
    {
        return $this->hasOne(ProductBundle::class);
    }

    /** Other bundles that use this product as a component. */
    public function usedInBundles()
    {
        return $this->hasMany(ProductBundleItem::class, 'component_product_id');
    }
}
