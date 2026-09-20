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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_owner_id')->nullable()->index();
            $table->string('name');
            $table->string('symbol');
            $table->enum('type', ['weight', 'volume', 'length', 'quantity', 'packaging'])->default('quantity');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            // A tenant cannot define the same unit name twice; system units (shop_owner_id null) are also unique by name.
            $table->unique(['shop_owner_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
