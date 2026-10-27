<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditStockMigration extends Command
{
    protected $signature = 'stock:audit-migration';
    protected $description = 'Audit current stock data before migration to product_stocks table';

    public function handle()
    {
        $this->info('=== Stock Migration Audit ===');
        $this->newLine();

        // Count products with stock
        $totalProductsWithStock = DB::table('products')
            ->where('current_stock', '!=', 0)
            ->count();

        $this->info("Products with non-zero stock: {$totalProductsWithStock}");

        // Count products by branch attribution
        $byBranch = DB::table('products')
            ->select('branch_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(current_stock) as total_stock'))
            ->where('current_stock', '!=', 0)
            ->groupBy('branch_id')
            ->get();

        $this->newLine();
        $this->info('Stock by branch attribution:');
        $this->table(
            ['Branch ID', 'Product Count', 'Total Stock'],
            $byBranch->map(fn ($row) => [
                $row->branch_id ?? 'NULL',
                $row->count,
                $row->total_stock,
            ])->toArray()
        );

        // Count shops without branches
        $shopsWithoutBranches = DB::table('shop_owners')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('branches')
                    ->whereColumn('branches.shop_owner_id', 'shop_owners.id');
            })
            ->count();

        $this->newLine();
        $this->info("Shops without any branches: {$shopsWithoutBranches}");

        // Check for invalid branch references
        $invalidBranches = DB::table('products')
            ->select('products.id', 'products.branch_id', 'products.shop_owner_id')
            ->where('current_stock', '!=', 0)
            ->whereNotNull('branch_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('branches')
                    ->whereColumn('branches.id', 'products.branch_id')
                    ->whereColumn('branches.shop_owner_id', 'products.shop_owner_id');
            })
            ->count();

        $this->newLine();
        $this->info("Products with invalid branch references: {$invalidBranches}");

        // Summary
        $this->newLine();
        $this->info('=== Migration Readiness Assessment ===');
        
        if ($totalProductsWithStock === 0) {
            $this->warn('No stock to migrate. Migration is safe.');
        } else {
            $nullBranchCount = $byBranch->firstWhere('branch_id', null)?->count ?? 0;
            $migratableCount = $totalProductsWithStock - $nullBranchCount - $invalidBranches;
            
            $this->info("Total products with stock: {$totalProductsWithStock}");
            $this->info("Migratable (valid branch): {$migratableCount}");
            $this->info("Unresolved (no branch): {$nullBranchCount}");
            $this->info("Invalid branch refs: {$invalidBranches}");
            
            if ($migratableCount === $totalProductsWithStock) {
                $this->info('✓ All stock can be migrated automatically.');
            } else {
                $this->warn('⚠ Some stock requires manual reconciliation.');
            }
        }

        return Command::SUCCESS;
    }
}
