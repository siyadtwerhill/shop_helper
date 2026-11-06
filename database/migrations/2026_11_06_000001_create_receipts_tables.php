<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('sequence_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['shop_owner_id', 'branch_id', 'sequence_date'], 'receipt_sequence_scope_unique');
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number', 64);
            $table->string('receipt_type', 24)->default('sale');
            $table->string('status', 24)->default('issued');
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('document_snapshot');
            $table->timestamps();
            $table->unique(['shop_owner_id', 'receipt_number'], 'receipts_shop_number_unique');
            $table->unique(['shop_owner_id', 'sale_id', 'receipt_type'], 'receipts_sale_type_unique');
            $table->index(['shop_owner_id', 'branch_id', 'issued_at'], 'receipts_scope_date_index');
        });

        Schema::create('receipt_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 16)->default('print');
            $table->timestamp('printed_at');
            $table->timestamps();
            $table->index(['shop_owner_id', 'receipt_id', 'printed_at'], 'receipt_print_logs_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_print_logs');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('receipt_sequences');
    }
};