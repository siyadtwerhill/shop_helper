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
        Schema::table('field_definitions', function (Blueprint $table) {
            // Drop the old unique on 'key' if it exists
            $schemaManager = DB::getDoctrineSchemaManager();
            $tableInfo = $schemaManager->listTableDetails('field_definitions');

            if ($tableInfo->hasIndex('field_definitions_key_unique')) {
                $table->dropUnique(['key']);
            }

            // Add the composite unique
            $table->unique(['shop_owner_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_definitions', function (Blueprint $table) {
            $table->dropUnique(['shop_owner_id', 'key']);
            $table->unique(['key']);
        });
    }
};
