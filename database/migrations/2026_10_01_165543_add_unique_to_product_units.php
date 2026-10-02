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
        $duplicates = DB::table('product_units')
            ->select('product_id', 'unit_id')
            ->groupBy('product_id', 'unit_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $ids = DB::table('product_units')
                ->where('product_id', $dup->product_id)
                ->where('unit_id', $dup->unit_id)
                ->orderBy('id')
                ->pluck('id');

            if ($ids->count() > 1) {
                DB::table('product_units')
                    ->whereIn('id', $ids->slice(1))
                    ->delete();
            }
        }

        if (Schema::hasIndex('product_units', 'product_units_product_id_unit_id_unique')) {
            return;
        }

        Schema::table('product_units', function (Blueprint $table) {
            $table->unique(['product_id', 'unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasIndex('product_units', 'product_units_product_id_unit_id_unique')) {
            return;
        }

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'unit_id']);
        });
    }
};
