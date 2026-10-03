<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_bundle_items', function (Blueprint $t) {
            if (! Schema::hasColumn('product_bundle_items', 'component_variant_id')) {
                $t->unsignedBigInteger('component_variant_id')->nullable()->after('component_product_id')->index();
            }
        });

        // Widen quantity from whatever it is now to DECIMAL(15,4).
        DB::statement('ALTER TABLE product_bundle_items MODIFY quantity DECIMAL(15,4) NOT NULL');
    }

    public function down(): void
    {
        Schema::table('product_bundle_items', function (Blueprint $t) {
            if (Schema::hasColumn('product_bundle_items', 'component_variant_id')) {
                $t->dropColumn('component_variant_id');
            }
        });
        DB::statement('ALTER TABLE product_bundle_items MODIFY quantity DECIMAL(8,2) NOT NULL');
    }
};
