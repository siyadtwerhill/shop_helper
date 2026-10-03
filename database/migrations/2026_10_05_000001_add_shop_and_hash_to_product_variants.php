<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Columns ─────────────────────────────────────────────────────────
        // Both columns may already exist (applied manually or by an earlier run).
        Schema::table('product_variants', function (Blueprint $t) {
            if (! Schema::hasColumn('product_variants', 'shop_owner_id')) {
                $t->unsignedBigInteger('shop_owner_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('product_variants', 'attributes_hash')) {
                $t->string('attributes_hash', 64)->nullable();
            }
        });

        // ── Backfill shop_owner_id where still NULL ─────────────────────────
        DB::statement('UPDATE product_variants v JOIN products p ON p.id = v.product_id SET v.shop_owner_id = p.shop_owner_id WHERE v.shop_owner_id IS NULL');

        // ── Backfill attributes_hash where still NULL ───────────────────────
        // Same normalisation as ProductVariantService::hashFor — keep them identical.
        $hash = function (array $attrs): string {
            $n = [];
            foreach ($attrs as $k => $v) {
                $v = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $v)));
                if ($v !== '') $n[$k] = $v;
            }
            ksort($n);
            return sha1(json_encode($n, JSON_UNESCAPED_UNICODE));
        };

        $seen  = [];
        $dupes = [];
        DB::table('product_variants')
            ->whereNull('deleted_at')
            ->whereNull('attributes_hash')
            ->chunkById(500, function ($rows) use (&$seen, &$dupes, $hash) {
                foreach ($rows as $r) {
                    $h   = $hash(json_decode($r->attributes ?? '[]', true) ?: []);
                    $key = $r->product_id . '|' . $h;
                    if (isset($seen[$key])) {
                        $dupes[] = $r->id;
                        continue;
                    }
                    $seen[$key] = true;
                    DB::table('product_variants')->where('id', $r->id)->update(['attributes_hash' => $h]);
                }
            });

        if ($dupes) {
            Log::warning('Duplicate variant combinations left without a hash (review by hand): ' . implode(',', $dupes));
        }

        // ── Indexes ─────────────────────────────────────────────────────────
        // Check existing index names before adding or dropping.
        $existingIndexes = collect(DB::select('SHOW INDEX FROM product_variants'))->pluck('Key_name')->unique()->all();

        Schema::table('product_variants', function (Blueprint $t) use ($existingIndexes) {
            // shop_owner_id plain index
            if (! in_array('product_variants_shop_owner_id_index', $existingIndexes)) {
                $t->index('shop_owner_id');
            }

            // (product_id, attributes_hash) unique — may already exist under a different name
            if (! in_array('product_variants_product_hash_unique', $existingIndexes)
                && ! in_array('product_variants_product_id_attributes_hash_unique', $existingIndexes)) {
                $t->unique(['product_id', 'attributes_hash'], 'product_variants_product_hash_unique');
            }

            // Drop the global SKU unique, replace with shop-scoped one
            if (in_array('product_variants_sku_unique', $existingIndexes)) {
                $t->dropUnique('product_variants_sku_unique');
            }

            if (! in_array('product_variants_shop_sku_unique', $existingIndexes)) {
                $t->unique(['shop_owner_id', 'sku'], 'product_variants_shop_sku_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $t) {
            $existingIndexes = collect(DB::select('SHOW INDEX FROM product_variants'))->pluck('Key_name')->unique()->all();

            if (in_array('product_variants_shop_sku_unique', $existingIndexes)) {
                $t->dropUnique('product_variants_shop_sku_unique');
            }
            if (in_array('product_variants_product_hash_unique', $existingIndexes)) {
                $t->dropUnique('product_variants_product_hash_unique');
            }
            if (in_array('product_variants_shop_owner_id_index', $existingIndexes)) {
                $t->dropIndex(['shop_owner_id']);
            }
            if (Schema::hasColumn('product_variants', 'attributes_hash')) {
                $t->dropColumn('attributes_hash');
            }
            if (Schema::hasColumn('product_variants', 'shop_owner_id')) {
                $t->dropColumn('shop_owner_id');
            }
        });
    }
};
