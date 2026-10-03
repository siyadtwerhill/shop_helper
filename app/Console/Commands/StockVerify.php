<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StockVerify extends Command
{
    protected $signature = 'stock:verify {--fix : Rebuild products.current_stock from the movement ledger}';
    protected $description = 'Check cached stock against the ledger and report ledger anomalies';

    public function handle(): int
    {
        $drift = DB::select(<<<'SQL'
            SELECT p.id, p.shop_owner_id, p.name, p.current_stock AS cached, COALESCE(SUM(m.base_quantity), 0) AS ledger
            FROM products p LEFT JOIN inventory_movements m ON m.product_id = p.id
            GROUP BY p.id, p.shop_owner_id, p.name, p.current_stock
            HAVING ABS(p.current_stock - COALESCE(SUM(m.base_quantity), 0)) > 0.0001
        SQL);
        $this->report('Cached stock differs from the ledger', $drift);

        if ($this->option('fix') && $drift) {
            DB::statement('UPDATE products p LEFT JOIN (SELECT product_id, SUM(base_quantity) s FROM inventory_movements GROUP BY product_id) l
                           ON l.product_id = p.id SET p.current_stock = COALESCE(l.s, 0)');
            $this->info('Rebuilt cached stock from the ledger.');
            $drift = [];
        }

        $other = 0;
        $checks = [
            'Bundle products with their own ledger rows (phantom stock)' => <<<'SQL'
                SELECT p.id, p.name, COUNT(*) AS rows_n, SUM(m.base_quantity) AS net
                FROM inventory_movements m JOIN product_bundles b ON b.product_id = m.product_id JOIN products p ON p.id = m.product_id
                GROUP BY p.id, p.name
            SQL,
            'Tracked products with negative stock' => <<<'SQL'
                SELECT id, name, current_stock FROM products
                WHERE current_stock < 0 AND track_stock = 1 AND allow_negative_stock = 0 AND stock_mode <> 'from_components'
            SQL,
            'Movements pointing at another product\'s variant' => <<<'SQL'
                SELECT m.id, m.product_id, m.variant_id FROM inventory_movements m
                JOIN product_variants v ON v.id = m.variant_id WHERE v.product_id <> m.product_id
            SQL,
            'Movements pointing at another product\'s unit' => <<<'SQL'
                SELECT m.id, m.product_id, m.unit_id FROM inventory_movements m
                JOIN product_units u ON u.id = m.unit_id WHERE u.product_id <> m.product_id
            SQL,
            'Products without exactly one base unit' => <<<'SQL'
                SELECT p.id, p.name, COUNT(u.id) AS base_units FROM products p
                LEFT JOIN product_units u ON u.product_id = p.id AND u.is_base = 1
                GROUP BY p.id, p.name HAVING COUNT(u.id) <> 1
            SQL,
            'Base units whose conversion factor is not 1' => <<<'SQL'
                SELECT product_id, id AS product_unit_id, conversion_factor FROM product_units WHERE is_base = 1 AND conversion_factor <> 1
            SQL,
            'Variant products with stock no variant owns (use "assign stock")' => <<<'SQL'
                SELECT p.id, p.name, p.current_stock, COALESCE(SUM(m.base_quantity), 0) AS in_variants
                FROM products p LEFT JOIN inventory_movements m ON m.product_id = p.id AND m.variant_id IS NOT NULL
                WHERE p.stock_mode = 'from_variants'
                GROUP BY p.id, p.name, p.current_stock
                HAVING ABS(p.current_stock - COALESCE(SUM(m.base_quantity), 0)) > 0.0001
            SQL,
            'Variant ledger rows on products that do not use variants' => <<<'SQL'
                SELECT m.id, m.product_id, m.variant_id FROM inventory_movements m
                JOIN products p ON p.id = m.product_id WHERE m.variant_id IS NOT NULL AND p.stock_mode <> 'from_variants'
            SQL,
            'Bundle components that are bundles or missing' => <<<'SQL'
                SELECT i.id, i.product_bundle_id, i.component_product_id FROM product_bundle_items i
                LEFT JOIN products p ON p.id = i.component_product_id
                WHERE p.id IS NULL OR p.stock_mode = 'from_components'
            SQL,
            'Bundle items whose unit or variant belongs to another product' => <<<'SQL'
                SELECT i.id, i.component_product_id FROM product_bundle_items i
                LEFT JOIN product_units u ON u.id = i.unit_id
                LEFT JOIN product_variants v ON v.id = i.component_variant_id
                WHERE u.product_id <> i.component_product_id
                   OR (i.component_variant_id IS NOT NULL AND v.product_id <> i.component_product_id)
            SQL,
        ];

        foreach ($checks as $title => $sql) {
            $other += $this->report($title, DB::select($sql));
        }

        return ($drift || $other) ? self::FAILURE : self::SUCCESS;
    }

    private function report(string $title, array $rows): int
    {
        if (! $rows) {
            $this->line("<info>OK</info>   {$title}");
            return 0;
        }

        $this->error(count($rows) . "  {$title}");
        $this->table(array_keys((array) $rows[0]), array_map(fn ($r) => (array) $r, array_slice($rows, 0, 20)));
        return count($rows);
    }
}
