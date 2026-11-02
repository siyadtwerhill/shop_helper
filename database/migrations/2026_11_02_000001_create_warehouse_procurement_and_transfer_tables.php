<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['shop_owner_id', 'code']);
            $table->index(['shop_owner_id', 'is_active']);
        });

        $this->createIfMissing('warehouse_product_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_stock', 15, 4)->default(0);
            $table->decimal('reorder_level', 15, 4)->nullable();
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id'], 'warehouse_product_unique');
            $table->index(['shop_owner_id', 'warehouse_id']);
        });

        $this->createIfMissing('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('tax_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['shop_owner_id', 'name']);
        });

        $this->createIfMissing('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('location_type', 20);
            $table->unsignedBigInteger('location_id');
            $table->string('status', 20)->default('draft');
            $table->string('reference')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['shop_owner_id', 'status', 'created_at']);
            $table->index(['location_type', 'location_id']);
        });

        $this->createIfMissing('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();
            $table->index(['purchase_id', 'product_id']);
        });

        $this->createIfMissing('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');
            $table->string('destination_type', 20);
            $table->unsignedBigInteger('destination_id');
            $table->string('status', 20)->default('draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['shop_owner_id', 'status', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['destination_type', 'destination_id']);
        });

        $this->createIfMissing('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->timestamps();
            $table->index(['stock_transfer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        foreach (['stock_transfer_items', 'stock_transfers', 'purchase_items', 'purchases', 'suppliers', 'warehouse_product_stocks', 'warehouses'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Cannot drop {$table} while it contains records. Preserve procurement and stock history with a reviewed forward migration.");
            }
        }

        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('warehouse_product_stocks');
        Schema::dropIfExists('warehouses');
    }

    private function createIfMissing(string $name, Closure $definition): void
    {
        if (! Schema::hasTable($name)) Schema::create($name, $definition);
    }
};
