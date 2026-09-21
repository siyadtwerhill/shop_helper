<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('product_units')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->timestamps();

            $table->unique(['product_bundle_id', 'component_product_id', 'unit_id'], 'bundle_component_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundle_items');
    }
};
