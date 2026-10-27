<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Backfills product_stocks table from products.current_stock.
     * 
     * RECONCILIATION-FIRST APPROACH:
     * - Only migrates stock when products.branch_id is reliable and non-null
     * - Products without reliable branch attribution are NOT migrated
     * - products.current_stock is PRESERVED (not dropped)
     * - Shop owners must reconcile unresolved stock via admin workflow
     * 
     * This is a NON-DESTRUCTIVE migration. It adds branch-specific stock
     * records but does not remove or modify the legacy products.current_stock column.
     */
    public function up(): void
    {
        // Step 1: Create a reconciliation table to track migration status
        Schema::create('stock_reconciliation_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('target_branch_id')->nullable();
            $table->decimal('original_stock', 15, 4);
            $table->string('reconciliation_status')->default('pending'); // pending, migrated, unresolved
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['product_id', 'reconciliation_status']);
        });

        // Step 2: Inventory existing stock data
        $inventory = DB::table('products')
            ->select([
                'id as product_id',
                'shop_owner_id',
                'branch_id',
                'current_stock as original_stock',
            ])
            ->where('current_stock', '!=', 0)
            ->get();

        // Step 3: Classify and migrate only trustworthy stock
        foreach ($inventory as $item) {
            $status = 'unresolved';
            $targetBranchId = null;
            $notes = null;

            // Only migrate if branch attribution is reliable and non-null
            if ($item->branch_id && $item->branch_id > 0) {
                // Verify the branch actually exists and belongs to the shop
                $branchExists = DB::table('branches')
                    ->where('id', $item->branch_id)
                    ->where('shop_owner_id', $item->shop_owner_id)
                    ->exists();

                if ($branchExists) {
                    // Migrate to product_stocks
                    DB::table('product_stocks')->insert([
                        'product_id' => $item->product_id,
                        'branch_id' => $item->branch_id,
                        'shop_owner_id' => $item->shop_owner_id,
                        'current_stock' => $item->original_stock,
                        'reorder_level' => null, // Will be set per-branch later
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $status = 'migrated';
                    $targetBranchId = $item->branch_id;
                    $notes = 'Migrated from products.current_stock';
                } else {
                    $notes = 'Invalid branch_id in products table';
                }
            } else {
                $notes = 'No branch attribution in products table';
            }

            // Log reconciliation status
            DB::table('stock_reconciliation_log')->insert([
                'product_id' => $item->product_id,
                'shop_owner_id' => $item->shop_owner_id,
                'target_branch_id' => $targetBranchId,
                'original_stock' => $item->original_stock,
                'reconciliation_status' => $status,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Step 4: Generate summary report
        $summary = DB::table('stock_reconciliation_log')
            ->selectRaw('
                reconciliation_status,
                COUNT(*) as count,
                SUM(original_stock) as total_stock
            ')
            ->groupBy('reconciliation_status')
            ->get();

        // Log summary to migration output ( Laravel will display this)
        echo "\n=== Stock Migration Summary ===\n";
        foreach ($summary as $row) {
            echo "{$row->reconciliation_status}: {$row->count} products, total stock: {$row->total_stock}\n";
        }
        echo "================================\n\n";
    }

    /**
     * Reverse the migrations.
     * 
     * WARNING: This is a destructive rollback.
     * It removes the backfilled product_stocks and reconciliation log.
     * The products.current_stock column is NOT restored from product_stocks
     * because stock may have been modified in product_stocks after migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_reconciliation_log');
        Schema::dropIfExists('product_stocks');
    }
};
