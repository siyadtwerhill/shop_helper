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
        Schema::create('field_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_owner_id')->nullable();
            $table->foreign('shop_owner_id')->references('id')->on('shop_owners')->onDelete('cascade');
            $table->string('key')->unique();
            $table->string('label');
            $table->enum('type', ['text', 'textarea', 'number', 'decimal', 'select', 'multi_select', 'checkbox', 'date', 'boolean', 'json']);
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_definitions');
    }
};
