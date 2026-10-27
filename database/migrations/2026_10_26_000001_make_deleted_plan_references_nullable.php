<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['pending_plan_id']);
        });
        Schema::table('subscription_changes', function (Blueprint $table) {
            $table->dropForeign(['from_plan_id']);
            $table->dropForeign(['to_plan_id']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        Schema::table('subscription_changes', function (Blueprint $table) {
            $table->unsignedBigInteger('to_plan_id')->nullable()->change();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('pending_plan_id')->references('id')->on('plans')->nullOnDelete();
        });
        Schema::table('subscription_changes', function (Blueprint $table) {
            $table->foreign('from_plan_id')->references('id')->on('plans')->nullOnDelete();
            $table->foreign('to_plan_id')->references('id')->on('plans')->nullOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $freePlanId = DB::table('plans')->where('slug', 'free')->value('id');
        $hasNullPlanReferences = DB::table('subscription_changes')->whereNull('to_plan_id')->exists()
            || DB::table('payments')->whereNull('plan_id')->exists();

        if ($hasNullPlanReferences && ! $freePlanId) {
            throw new RuntimeException('A Free plan is required to restore deleted plan references.');
        }

        if ($freePlanId) {
            DB::table('subscription_changes')->whereNull('to_plan_id')->update(['to_plan_id' => $freePlanId]);
            DB::table('payments')->whereNull('plan_id')->update(['plan_id' => $freePlanId]);
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['pending_plan_id']);
        });
        Schema::table('subscription_changes', function (Blueprint $table) {
            $table->dropForeign(['from_plan_id']);
            $table->dropForeign(['to_plan_id']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        Schema::table('subscription_changes', function (Blueprint $table) {
            $table->unsignedBigInteger('to_plan_id')->nullable(false)->change();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable(false)->change();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('pending_plan_id')->references('id')->on('plans');
        });
        Schema::table('subscription_changes', function (Blueprint $table) {
            $table->foreign('from_plan_id')->references('id')->on('plans');
            $table->foreign('to_plan_id')->references('id')->on('plans');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans');
        });
    }
};
