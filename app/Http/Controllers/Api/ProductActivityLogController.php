<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;

class ProductActivityLogController extends Controller
{
    /** Product Details > Activity tab (spec §15). */
    public function index(Product $product)
    {
        return $product->activityLogs()->with('user')->paginate(25);
    }
}
