<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            // The Product row that represents the sellable bundle itself (e.g. "Breakfast Set").
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('bundle_price', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bundles');
    }
};
