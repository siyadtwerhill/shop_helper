<?php

use App\Services\ProductStockBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_stocks')) {
            throw new RuntimeException('Run the product_stocks schema migration before this backfill.');
        }

        if (! Schema::hasTable('stock_reconciliation_log')) {
            Schema::create('stock_reconciliation_log', function (Blueprint $table): void {
                $table->id();
                // These are historical identifiers, deliberately not foreign keys.
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('shop_owner_id');
                $table->string('shop_name_snapshot')->nullable();
                $table->string('product_name_snapshot');
                $table->string('sku_snapshot')->nullable();
                $table->unsignedBigInteger('source_branch_id')->nullable();
                $table->string('source_branch_name_snapshot')->nullable();
                $table->unsignedBigInteger('destination_branch_id')->nullable();
                $table->string('destination_branch_name_snapshot')->nullable();
                $table->decimal('original_stock', 15, 4);
                $table->string('status', 32);
                $table->string('reason_code', 32);
                $table->text('reason')->nullable();
                $table->timestamps();
                $table->unique('product_id', 'stock_reconciliation_product_unique');
                $table->index(['shop_owner_id', 'status']);
            });
        }

        // RefreshDatabase rebuilds the test schema; never copy real stock in that path.
        if (app()->environment('testing')) {
            return;
        }

        app(ProductStockBackfillService::class)->backfill();
    }

    public function down(): void
    {
        throw new RuntimeException('This stock data migration is forward-only. Preserve product_stocks and reconciliation evidence.');
    }
};
