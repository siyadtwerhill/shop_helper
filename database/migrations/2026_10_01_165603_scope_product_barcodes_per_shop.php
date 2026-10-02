<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->unsignedBigInteger('shop_owner_id')->nullable()->after('id');
            $table->foreign('shop_owner_id')->references('id')->on('shop_owners')->onDelete('cascade');
        });

        DB::update('
            UPDATE product_barcodes
            SET shop_owner_id = (
                SELECT products.shop_owner_id
                FROM products
                WHERE products.id = product_barcodes.product_id
            )
        ');

        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->unique(['shop_owner_id', 'barcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_barcodes', function (Blueprint $table) {
            $table->dropUnique(['shop_owner_id', 'barcode']);
            $table->dropForeign(['shop_owner_id']);
            $table->dropColumn('shop_owner_id');
            $table->unique('barcode');
        });
    }
};
