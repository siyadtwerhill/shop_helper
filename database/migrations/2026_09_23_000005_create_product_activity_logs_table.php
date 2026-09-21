<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unified, human-readable audit feed (spec §15) — distinct from inventory_movements,
     * which is the precise decimal ledger. This table is what the Product Details > Activity
     * tab renders: price changes, cost changes, stock adjustments, barcode additions, etc.,
     * all in one chronological list.
     */
    public function up(): void
    {
        Schema::create('product_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // price_changed, cost_changed, stock_adjusted, barcode_added, variant_added, bundle_created, status_changed
            $table->string('field')->nullable();
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_activity_logs');
    }
};
