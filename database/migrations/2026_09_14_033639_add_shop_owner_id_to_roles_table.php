<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('shop_owner_id')->nullable()->after('id');
            $table->index('shop_owner_id');
            $table->foreign('shop_owner_id')->references('id')->on('shop_owners')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['shop_owner_id']);
            $table->dropIndex(['shop_owner_id']);
            $table->dropColumn('shop_owner_id');
        });
    }
};
