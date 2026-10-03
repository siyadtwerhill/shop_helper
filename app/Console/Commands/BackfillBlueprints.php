<?php

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Models\Product;
use App\Models\ShopOwner;
use App\Services\BlueprintPresetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillBlueprints extends Command
{
    protected $signature = 'blueprints:backfill';
    protected $description = 'Give every shop a default Generic blueprint and attach existing products to it';

    public function handle(BlueprintPresetService $presets): int
    {
        ShopOwner::query()->chunkById(100, function ($shops) use ($presets) {
            foreach ($shops as $shop) {
                DB::transaction(function () use ($shop, $presets) {
                    $bp = Blueprint::where('shop_owner_id', $shop->id)->where('is_default', true)->first()
                        ?? $presets->createGenericBlueprint($shop);

                    $products = Product::withTrashed()->where('shop_owner_id', $shop->id);

                    // Keep existing variant and bundle products working under Generic.
                    $caps = is_array($bp->capabilities) ? $bp->capabilities : (json_decode($bp->capabilities, true) ?? []);
                    $caps['variants'] = ($caps['variants'] ?? false) || (clone $products)->where('product_type', 'variant')->exists();
                    $caps['bundles']  = ($caps['bundles'] ?? false)  || (clone $products)->where('product_type', 'bundle')->exists();
                    $caps['decimal_quantities'] = true;
                    $bp->update(['capabilities' => $caps]);

                    // Ensure legacy variant products can edit their variants from this blueprint.
                    if (! empty($caps['variants'])) {
                        $presets->ensureVariantAxes($bp);
                    }

                    (clone $products)->whereNull('blueprint_id')->update([
                        'blueprint_id' => $bp->id,
                        'blueprint_version' => $bp->version,
                        'stock_mode' => DB::raw("CASE product_type WHEN 'variant' THEN 'from_variants' WHEN 'bundle' THEN 'from_components' ELSE 'own' END"),
                    ]);

                    $odd = (clone $products)->where('product_type', 'neutral')
                        ->whereIn('id', DB::table('product_variants')->select('product_id'))->count();
                    if ($odd > 0) {
                        $this->warn("Shop #{$shop->id}: {$odd} plain products already have variants. Review by hand.");
                    }
                });
            }
        });

        $this->info('Done.');
        return self::SUCCESS;
    }
}
