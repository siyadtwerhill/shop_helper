<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product_variants (spec §10): each variant can have its own SKU, barcode, stock, price, image.
     * Barcode lives in product_barcodes (product_variant_id). Stock lives in inventory_movements
     * (variant_id) — a variant does not get its own current_stock column; it shares the parent
     * product's stock-tracking pattern via the ledger, filtered by variant_id.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->json('attributes'); // e.g. {"color": "Black", "size": "S"}
            $table->decimal('selling_price', 15, 2)->nullable(); // overrides the unit's price when set
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->string('image_path')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
