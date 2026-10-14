<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sku_counters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_owner_id')->constrained('shop_owners')->cascadeOnDelete();
            $t->string('prefix', 10);
            $t->unsignedBigInteger('last_number')->default(0);
            $t->unique(['shop_owner_id', 'prefix']);
        });

        // Drop the global SKU unique constraint and replace with shop-scoped
        $indexes = collect(Schema::getIndexes('products'))->pluck('name');
        if ($indexes->contains('products_sku_unique')) {
            Schema::table('products', fn (Blueprint $t) => $t->dropUnique('products_sku_unique'));
        }

        Schema::table('products', function (Blueprint $t) {
            $t->unique(['shop_owner_id', 'sku']);
        });

        // Seed each counter from the highest existing number, archived (soft-deleted) rows included.
        $max = [];
        DB::table('products')->select('id', 'shop_owner_id', 'sku')->whereNotNull('sku')->orderBy('id')
            ->chunk(1000, function ($rows) use (&$max) {
                foreach ($rows as $r) {
                    if (preg_match('/^([A-Z]{1,10})-(\d+)$/', $r->sku, $m)) {
                        $key = $r->shop_owner_id . '|' . $m[1];
                        $max[$key] = max($max[$key] ?? 0, (int) $m[2]);
                    }
                }
            });

        foreach ($max as $key => $n) {
            [$shop, $prefix] = explode('|', $key);
            DB::table('sku_counters')->insert(['shop_owner_id' => $shop, 'prefix' => $prefix, 'last_number' => $n]);
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('sku_counters');

        // Restore the global SKU unique constraint
        Schema::table('products', function (Blueprint $t) {
            $t->dropUnique(['shop_owner_id', 'sku']);
            $t->unique('sku');
        });
    }
};
