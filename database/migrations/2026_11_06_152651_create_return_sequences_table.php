<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('sequence_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['shop_owner_id', 'branch_id', 'sequence_date'], 'return_sequence_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_sequences');
    }
};
