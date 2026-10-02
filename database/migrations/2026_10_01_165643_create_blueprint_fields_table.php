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
        Schema::create('blueprint_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blueprint_id');
            $table->foreign('blueprint_id')->references('id')->on('blueprints')->onDelete('cascade');
            $table->unsignedBigInteger('field_definition_id');
            $table->foreign('field_definition_id')->references('id')->on('field_definitions')->onDelete('cascade');
            $table->string('section')->default('details');
            $table->integer('sort_order')->default(0);
            $table->boolean('required')->default(false);
            $table->boolean('show_in_list')->default(false);
            $table->boolean('show_in_pos')->default(false);
            $table->boolean('show_on_label')->default(false);
            $table->boolean('is_variant_axis')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('hidden')->default(false);
            $table->timestamps();

            $table->unique(['blueprint_id', 'field_definition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprint_fields');
    }
};
