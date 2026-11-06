<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('return_number', 64);
            $table->enum('status', ['pending', 'approved', 'reject', 'voided'])->default('pending');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_refund_amount', 15, 4)->default(0);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_owner_id', 'return_number'], 'product_returns_shop_number_unique');
            $table->index(['shop_owner_id', 'branch_id', 'status'], 'product_returns_scope_status_index');
            $table->index(['shop_owner_id', 'sale_id'], 'product_returns_sale_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_returns');
    }
};
