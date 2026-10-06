<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\ResolvesShop;
use Illuminate\Http\Request;

class ProductActivityLogController extends Controller
{
    use ResolvesShop;

    /** Product Details > Activity tab (spec §15). */
    public function index(Request $request, Product $product)
    {
        $this->ownedProduct($request, $product);
        return $product->activityLogs()->with('user')->paginate(25);
    }
}
