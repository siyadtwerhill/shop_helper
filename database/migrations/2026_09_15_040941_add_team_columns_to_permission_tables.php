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
        // Add shop_owner_id column to model_has_permissions table
        if (!Schema::hasColumn('model_has_permissions', 'shop_owner_id')) {
            Schema::table('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('shop_owner_id')->nullable()->after('model_type');
                $table->index('shop_owner_id');
                $table->foreign('shop_owner_id')
                    ->references('id')
                    ->on('shop_owners')
                    ->onDelete('cascade');
            });
        }

        // Add shop_owner_id column to model_has_roles table
        if (!Schema::hasColumn('model_has_roles', 'shop_owner_id')) {
            Schema::table('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('shop_owner_id')->nullable()->after('model_type');
                $table->index('shop_owner_id');
                $table->foreign('shop_owner_id')
                    ->references('id')
                    ->on('shop_owners')
                    ->onDelete('cascade');
            });
        }

        // Update primary keys to include shop_owner_id for proper team scoping
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropPrimary(['model_id', 'permission_id', 'model_type']);
            $table->primary(['model_id', 'permission_id', 'model_type', 'shop_owner_id']);
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary(['model_id', 'role_id', 'model_type']);
            $table->primary(['model_id', 'role_id', 'model_type', 'shop_owner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert primary keys
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropPrimary(['model_id', 'permission_id', 'model_type', 'shop_owner_id']);
            $table->primary(['model_id', 'permission_id', 'model_type']);
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary(['model_id', 'role_id', 'model_type', 'shop_owner_id']);
            $table->primary(['model_id', 'role_id', 'model_type']);
        });

        // Drop foreign keys and columns
        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign(['shop_owner_id']);
            $table->dropIndex(['shop_owner_id']);
            $table->dropColumn('shop_owner_id');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign(['shop_owner_id']);
            $table->dropIndex(['shop_owner_id']);
            $table->dropColumn('shop_owner_id');
        });
    }
};
