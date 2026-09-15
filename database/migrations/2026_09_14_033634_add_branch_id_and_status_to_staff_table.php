<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // null = shop-wide access (owners, regional roles).
            // Any real value scopes the staff member to that branch,
            // via the BelongsToBranch trait on branch-owned models.
            $table->foreignId('branch_id')->nullable()->after('shop_owner_id')
                ->constrained()->nullOnDelete();

            $table->enum('status', ['active', 'inactive'])->default('active')->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'status']);
        });
    }
};
