<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SkuGenerator
{
    /** Format {PREFIX}-{5 digits}, numbered per shop and prefix under a row lock. */
    public function generate(int $shopOwnerId, ?string $categoryName = null): string
    {
        $prefix = $this->prefixFor($categoryName);

        return DB::transaction(function () use ($shopOwnerId, $prefix) {
            DB::table('sku_counters')->insertOrIgnore([
                'shop_owner_id' => $shopOwnerId, 'prefix' => $prefix, 'last_number' => 0,
            ]);

            $row = DB::table('sku_counters')
                ->where('shop_owner_id', $shopOwnerId)->where('prefix', $prefix)
                ->lockForUpdate()->first();

            $next = $row->last_number;
            do {
                $next++;
                $sku = sprintf('%s-%05d', $prefix, $next);
                $taken = \App\Models\Product::withTrashed()->where('shop_owner_id', $shopOwnerId)->where('sku', $sku)->exists();
            } while ($taken && $next < $row->last_number + 1000);

            if ($taken) throw new \RuntimeException('Could not generate a unique SKU.');

            DB::table('sku_counters')->where('id', $row->id)->update(['last_number' => $next]);

            return $sku;
        });
    }

    private function prefixFor(?string $name): string
    {
        $letters = preg_replace('/[^A-Za-z]/', '', Str::ascii((string) $name));

        return $letters !== '' ? Str::upper(substr($letters, 0, 3)) : 'GEN';
    }
}