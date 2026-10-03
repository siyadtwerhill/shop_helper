<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            // Add new ID-based columns alongside existing JSON
            $table->unsignedBigInteger('default_base_unit_id')->nullable()->after('unit_policy');
            $table->json('allowed_unit_ids')->nullable()->after('default_base_unit_id');
        });

        // Migrate existing JSON data to new columns
        DB::statement("
            UPDATE blueprints
            SET default_base_unit_id = NULL,
                allowed_unit_ids = JSON_ARRAY()
            WHERE default_base_unit_id IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropColumn(['default_base_unit_id', 'allowed_unit_ids']);
        });
    }
};
