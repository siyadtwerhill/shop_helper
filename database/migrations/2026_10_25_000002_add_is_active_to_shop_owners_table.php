<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_owners', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('staff_count');
        });
    }

    public function down(): void
    {
        Schema::table('shop_owners', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
