<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('min_margin_type', 16)->nullable()->after('min_margin_percent');
            $table->decimal('min_margin_value', 15, 2)->nullable()->after('min_margin_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['min_margin_type', 'min_margin_value']);
        });
    }
};
