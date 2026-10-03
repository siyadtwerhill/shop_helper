<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Operational columns
            $table->decimal('min_stock', 15, 4)->nullable()->after('status');
            $table->decimal('reorder_quantity', 15, 4)->nullable()->after('min_stock');
            $table->boolean('track_stock')->default(true)->after('reorder_quantity');
            $table->boolean('allow_negative_stock')->default(false)->after('track_stock');
            $table->boolean('is_sellable')->default(true)->after('allow_negative_stock');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('is_sellable');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');

            // Indexes for common queries
            $table->index(['shop_owner_id', 'status']);
            $table->index(['shop_owner_id', 'category_id']);
            $table->index(['shop_owner_id', 'branch_id']);
            $table->index(['shop_owner_id', 'blueprint_id']);
            $table->index(['shop_owner_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeignId('updated_by');
            $table->dropForeignId('created_by');
            $table->dropColumn('is_sellable');
            $table->dropColumn('allow_negative_stock');
            $table->dropColumn('track_stock');
            $table->dropColumn('reorder_quantity');
            $table->dropColumn('min_stock');

            $table->dropIndex(['shop_owner_id', 'name']);
            $table->dropIndex(['shop_owner_id', 'blueprint_id']);
            $table->dropIndex(['shop_owner_id', 'branch_id']);
            $table->dropIndex(['shop_owner_id', 'category_id']);
            $table->dropIndex(['shop_owner_id', 'status']);
        });
    }
};
