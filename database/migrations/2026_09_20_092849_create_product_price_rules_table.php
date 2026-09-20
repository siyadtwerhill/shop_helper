<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product_price_rules — wholesale quantity tiers (spec §5: "1–9, 10–49, 50+").
     * Scoped per unit_id since a product's tiers can differ by unit (e.g. bag vs KG).
     * max_quantity = null means "and up" (an open-ended top tier).
     */
    public function up(): void
    {
        Schema::create('product_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('product_units')->cascadeOnDelete();

            $table->decimal('min_quantity', 15, 4);
            $table->decimal('max_quantity', 15, 4)->nullable();
            $table->decimal('price', 15, 2);

            $table->timestamps();

            $table->index(['product_id', 'unit_id', 'min_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_rules');
    }
};