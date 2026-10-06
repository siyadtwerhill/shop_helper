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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            // ASSUMPTION: a `sales` header table already exists in your POS flow.
            // Rename this FK (and the migration filename ordering) if yours is called `orders` etc.
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();

            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable(); // FK added once product_variants exists (Phase 5)
            $table->foreignId('unit_id')->constrained('product_units')->restrictOnDelete();

            $table->decimal('quantity', 15, 4);
            $table->decimal('base_quantity', 15, 4);
            $table->decimal('unit_price', 15, 2);           // actual price charged (may be negotiated)
            $table->decimal('original_unit_price', 15, 2);  // list price — never overwritten (spec §5)
            $table->decimal('discount_amount', 15, 2)->default(0);

            $table->timestamps();

            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
