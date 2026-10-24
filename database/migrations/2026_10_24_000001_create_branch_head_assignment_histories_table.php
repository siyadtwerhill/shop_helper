<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for branch head assignment, replacement and removal.
     * Records who assigned a branch head, when, and what changed.
     */
    public function up(): void
    {
        Schema::create('branch_head_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('previous_head_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('new_head_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // assigned, replaced, removed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['shop_owner_id', 'branch_id']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_head_assignment_histories');
    }
};
