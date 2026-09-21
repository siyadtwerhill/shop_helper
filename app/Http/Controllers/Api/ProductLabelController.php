<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\LabelGeneratorService;

class ProductLabelController extends Controller
{
    public function __construct(private LabelGeneratorService $labels)
    {
    }

    public function show(Product $product)
    {
        return response()->json($this->labels->buildForProduct($product));
    }

    public function variant(Product $product, ProductVariant $variant)
    {
        abort_if($variant->product_id !== $product->id, 404);

        return response()->json($this->labels->buildForVariant($product, $variant));
    }
}
