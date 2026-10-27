<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('scope')->default('all')->after('slug');
            $table->foreignId('shop_owner_id')->nullable()->after('scope')
                ->constrained('shop_owners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropForeign(['shop_owner_id']);
            $table->dropColumn(['shop_owner_id', 'scope']);
        });
    }
};
