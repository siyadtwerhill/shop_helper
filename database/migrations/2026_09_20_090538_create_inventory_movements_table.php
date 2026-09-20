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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable(); // FK added once product_variants exists (Phase 5)
            $table->foreignId('unit_id')->constrained('product_units')->restrictOnDelete();

            $table->decimal('quantity', 15, 4);      // in the given unit; signed only for 'adjustment'
            $table->decimal('base_quantity', 15, 4);  // always signed: + = stock in, - = stock out

            $table->string('movement_type'); // purchase, sale, sale_return, purchase_return, adjustment, damage, opening_stock
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
