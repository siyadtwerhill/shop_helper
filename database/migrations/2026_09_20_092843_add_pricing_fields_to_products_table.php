<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pricing_mode: fixed | negotiable | price_range | wholesale (spec §5).
     * cost_price + min_margin_percent define the minimum selling price (spec §5 "margin protection").
     * min_price is only used in price_range mode (the configurable floor alongside the listed price).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('pricing_mode', ['fixed', 'negotiable', 'price_range', 'wholesale'])
                ->default('fixed')->after('base_unit_id');
            $table->decimal('cost_price', 15, 2)->nullable()->after('pricing_mode');
            $table->decimal('min_margin_percent', 5, 2)->nullable()->after('cost_price');
            $table->decimal('min_price', 15, 2)->nullable()->after('min_margin_percent');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'cost_price', 'min_margin_percent', 'min_price']);
        });
    }
};