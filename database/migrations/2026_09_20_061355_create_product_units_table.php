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
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();

            $table->decimal('conversion_factor', 15, 4)->default(1.0000);
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->decimal('purchase_price', 15, 2)->nullable();

            $table->boolean('is_base')->default(false);
            $table->boolean('is_sellable')->default(true);
            $table->boolean('is_purchasable')->default(true);

            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
            $table->index(['product_id', 'is_base']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
