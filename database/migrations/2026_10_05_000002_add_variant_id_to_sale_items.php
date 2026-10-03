<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('sale_items', 'variant_id')) {
            Schema::table('sale_items', function (Blueprint $t) {
                $t->unsignedBigInteger('variant_id')->nullable()->after('product_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sale_items', 'variant_id')) {
            Schema::table('sale_items', function (Blueprint $t) {
                $t->dropColumn('variant_id');
            });
        }
    }
};
