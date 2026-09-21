<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These columns already existed as loose unsignedBigInteger from Phases 1 and 3
     * (product_variants didn't exist yet at the time). Now that it does, wire the FKs.
     */
    public function up(): void
    {
        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_barcodes', fn (Blueprint $t) => $t->dropForeign(['product_variant_id']));
        Schema::table('inventory_movements', fn (Blueprint $t) => $t->dropForeign(['variant_id']));
        Schema::table('sale_items', fn (Blueprint $t) => $t->dropForeign(['variant_id']));
    }
};
