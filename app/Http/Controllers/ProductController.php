<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Services\BarcodeGenerator;
use App\Services\QrCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(
        private BarcodeGenerator $barcodeGenerator,
        private QrCodeGenerator $qrCodeGenerator,
    ) {}

    private function shop(Request $request)
    {
        return $request->user()->shopOwner;
    }

    public function index(Request $request)
    {
        $shop = $this->shop($request);

        $query = Product::where('shop_owner_id', $shop->id)
            ->with(['category', 'brand', 'barcodes']);

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku', 'like', "%{$term}%")
                  ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', $term));
            });
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('brand_id')) $query->where('brand_id', $request->brand_id);
        if ($request->filled('status')) $query->where('status', $request->status);

        return response()->json(['products' => $query->latest()->paginate(20)]);
    }

    public function show(Product $product)
    {
        return response()->json([
            'product' => $product->load(['category', 'brand', 'barcodes']),
        ]);
    }

    public function store(Request $request)
    {
        $shop = $this->shop($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'image' => 'nullable|image|max:2048',
            'status' => 'nullable|in:active,inactive,archived',

            // Scanner flow: if the product was created from an unknown
            // scanned barcode, this fills it instead of generating one.
            'scanned_barcode' => 'nullable|string|max:64|unique:product_barcodes,barcode',
        ]);

        $product = DB::transaction(function () use ($data, $shop, $request) {
            $product = Product::create([
                'shop_owner_id' => $shop->id,
                'category_id' => $data['category_id'] ?? null,
                'brand_id' => $data['brand_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'status' => $data['status'] ?? 'active',
                // sku auto-fills via the model's creating() hook
            ]);

            if ($request->hasFile('image')) {
                $product->update(['image_path' => $request->file('image')->store('products', 'public')]);
            }

            // Barcode: use the scanned one if this came from the
            // scanner's "create from unknown barcode" flow, otherwise
            // auto-generate a valid EAN-13.
            $barcodeValue = $data['scanned_barcode'] ?? $this->barcodeGenerator->generate();
            $barcodeType = isset($data['scanned_barcode']) ? 'scanned' : 'auto_generated';

            ProductBarcode::create([
                'product_id' => $product->id,
                'barcode' => $barcodeValue,
                'type' => $barcodeType,
                'is_primary' => true,
            ]);

            // QR encodes the SKU, generated after the product has an id/sku
            $qrPath = $this->qrCodeGenerator->generateFor($product);
            $product->update(['qr_path' => $qrPath]);

            return $product;
        });

        return response()->json([
            'message' => 'Product created.',
            'product' => $product->fresh(['category', 'brand', 'barcodes']),
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'image' => 'nullable|image|max:2048',
            'status' => 'sometimes|in:active,inactive,archived',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image_path) Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return response()->json(['message' => 'Product updated.', 'product' => $product->fresh(['category', 'brand', 'barcodes'])]);
    }

    /**
     * Archive, never hard-delete, once a product could plausibly have
     * transaction history — matches the spec's "archive instead of
     * destructive deletion" rule.
     */
    public function destroy(Product $product)
    {
        $product->update(['status' => 'archived']);
        $product->delete(); // soft delete

        return response()->json(['message' => 'Product archived.']);
    }

    /**
     * Camera scanner lookup — the barcode found by the scanner is
     * checked against product_barcodes; front end decides whether to
     * open the product or offer "Create Product" with the barcode pre-filled.
     */
    public function lookupByBarcode(Request $request)
    {
        $data = $request->validate(['barcode' => 'required|string']);

        $barcode = ProductBarcode::where('barcode', $data['barcode'])
            ->whereHas('product', fn ($q) => $q->where('shop_owner_id', $this->shop($request)->id))
            ->with('product.category', 'product.brand')
            ->first();

        if (!$barcode) {
            return response()->json(['found' => false, 'barcode' => $data['barcode']]);
        }

        return response()->json(['found' => true, 'product' => $barcode->product]);
    }
}
