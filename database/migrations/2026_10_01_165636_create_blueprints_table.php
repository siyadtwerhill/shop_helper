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
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_owner_id');
            $table->foreign('shop_owner_id')->references('id')->on('shop_owners')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('preset_key')->nullable();
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->json('capabilities')->nullable();
            $table->json('pricing_policy')->nullable();
            $table->json('unit_policy')->nullable();
            $table->json('layout')->nullable();
            $table->integer('version')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};
