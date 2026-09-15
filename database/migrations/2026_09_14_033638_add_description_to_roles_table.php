<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Requires config('permission.teams') = true and the Spatie Teams
        // migration already run, so `roles` already has a team foreign key
        // (default column name: team_id, or shop_owner_id if you set
        // 'team_foreign_key' => 'shop_owner_id' in config/permission.php).
        Schema::table('roles', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
