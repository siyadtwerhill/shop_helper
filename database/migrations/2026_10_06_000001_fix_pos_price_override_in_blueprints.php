<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix any blueprints with pos_price_override = 'anyone' to false
        DB::table('blueprints')
            ->whereJsonContains('pricing_policy->pos_price_override', 'anyone')
            ->update(['pricing_policy->pos_price_override' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed
    }
};
