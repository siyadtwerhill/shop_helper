<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_return_id')->constrained('product_returns')->restrictOnDelete();
            $table->string('refund_number', 64);
            $table->enum('refund_method', ['cash', 'card', 'mobile_wallet', 'bank_transfer']);
            $table->decimal('refund_amount', 15, 4);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('reference_number')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_owner_id', 'refund_number'], 'refunds_shop_number_unique');
            $table->index(['shop_owner_id', 'branch_id', 'status'], 'refunds_scope_status_index');
            $table->index(['shop_owner_id', 'product_return_id'], 'refunds_return_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
