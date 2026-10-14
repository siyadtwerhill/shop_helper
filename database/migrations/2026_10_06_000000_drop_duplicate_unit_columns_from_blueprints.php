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
        if (Schema::hasColumn('blueprints', 'default_base_unit_id')) {
            Schema::table('blueprints', function (Blueprint $table) {
                $table->dropColumn('default_base_unit_id');
            });
        }

        if (Schema::hasColumn('blueprints', 'allowed_unit_ids')) {
            Schema::table('blueprints', function (Blueprint $table) {
                $table->dropColumn('allowed_unit_ids');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->unsignedBigInteger('default_base_unit_id')->nullable();
            $table->json('allowed_unit_ids')->nullable();
        });
    }
};
