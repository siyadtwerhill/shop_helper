<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_owners', function (Blueprint $table) {
            $table->string('address')->nullable()->after('location');
            $table->string('phone')->nullable()->after('address');
            $table->string('business_type')->nullable()->after('phone');
            $table->string('tax_id')->nullable()->after('business_type');
            $table->text('description')->nullable()->after('tax_id');
            $table->string('website')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_owners', function (Blueprint $table) {
            $table->dropColumn(['address', 'phone', 'business_type', 'tax_id', 'description', 'website']);
        });
    }
};
