<?php

namespace App\Console\Commands;

use App\Services\ProductStockBackfillService;
use Illuminate\Console\Command;

class PreviewProductStockBackfill extends Command
{
    protected $signature = 'stock:backfill-preview';

    protected $description = 'Preview product stock backfill outcomes without writing data';

    public function handle(ProductStockBackfillService $backfill): int
    {
        $rows = $backfill->preview();

        $this->table(
            ['Product ID', 'Shop', 'Product', 'SKU', 'Source branch', 'Destination branch', 'Original stock', 'Outcome', 'Reason'],
            $rows->map(fn (array $row): array => [
                $row['product_id'],
                $row['shop_name'] ?? 'Shop '.$row['shop_owner_id'],
                $row['product_name'],
                $row['sku'],
                $row['source_branch_id'] ?? '—',
                $row['destination_branch_id'] ? $row['destination_branch_id'].' / '.($row['destination_branch_name'] ?? 'unknown') : '-',
                $row['original_stock'],
                $row['status'],
                $row['reason'],
            ])->all()
        );

        $sum = fn (string $status): float => $rows
            ->where('status', $status)
            ->sum(fn (array $row): float => (float) $row['original_stock']);

        $this->newLine();
        $this->info('Dry run only; no database rows were written.');
        $this->line('Products in scope: '.$rows->count());
        $this->line('Original stock total: '.number_format($rows->sum(fn (array $row): float => (float) $row['original_stock']), 4, '.', ''));
        $this->line('Migrated stock proposed: '.number_format($sum('migrated'), 4, '.', '').' across '.$rows->where('status', 'migrated')->count().' stock rows');
        $this->line('Unresolved stock: '.number_format($sum('unresolved'), 4, '.', ''));
        $this->line('Zero-balance outcomes: '.$rows->where('status', 'zero_balance')->count());
        $this->line('Conflicts requiring manual review: '.$rows->where('status', 'conflict')->count());

        return self::SUCCESS;
    }
}
