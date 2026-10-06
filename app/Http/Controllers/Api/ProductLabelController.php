<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Traits\ResolvesShop;
use App\Services\LabelGeneratorService;
use Illuminate\Http\Request;

class ProductLabelController extends Controller
{
    use ResolvesShop;

    public function __construct(private LabelGeneratorService $labels)
    {
    }

    public function show(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);
        return response()->json($this->labels->buildForProduct($product));
    }

    public function variant(Request $request, Product $product, ProductVariant $variant)
    {
        $this->ownedProduct($request, $product);
        abort_if($variant->product_id !== $product->id, 404);

        return response()->json($this->labels->buildForVariant($product, $variant));
    }
}
