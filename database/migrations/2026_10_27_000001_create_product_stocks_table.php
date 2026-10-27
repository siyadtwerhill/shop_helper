<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates branch-specific stock tracking table.
     * Products remain in a shared catalog (shop-wide), but stock quantities
     * are tracked per branch. This enables multi-branch inventory without
     * duplicating product records.
     */
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            
            // Product reference (catalog is shop-wide)
            // Cascade delete is acceptable: if product is deleted, its stock tracking is no longer relevant
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            // Branch reference (stock is branch-specific)
            // NO cascade delete: preserve stock records for historical audit if branch is deleted
            // Branch deletion should be handled via soft delete and reconciliation workflow
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            
            // Tenant identifier for validation
            // Cascade delete is acceptable: if shop is deleted, all its data is removed
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            
            // Stock quantities
            $table->decimal('current_stock', 15, 4)->default(0);
            $table->decimal('reorder_level', 15, 4)->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Unique constraint: one stock record per product per branch
            $table->unique(['product_id', 'branch_id'], 'product_branch_unique');
            
            // Indexes for common queries
            $table->index(['shop_owner_id', 'branch_id']);
            $table->index(['branch_id', 'current_stock']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
