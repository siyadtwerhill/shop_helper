<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // First, regenerate all account codes with the new SR-XXXX format
        DB::table('users')->select('id')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                do {
                    $code = 'SR-' . Str::upper(Str::random(4));
                } while (DB::table('users')->where('account_code', $code)->exists());

                DB::table('users')->where('id', $user->id)->update(['account_code' => $code]);
            }
        });

        // Now safely shorten the column to accommodate SR-XXXX format
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_code', 8)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        // Revert to the old SP-XXXXXXXXXXXX format
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_code', 32)->nullable()->change();
        });

        DB::table('users')->select('id')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                do {
                    $code = 'SP-' . Str::upper(Str::random(12));
                } while (DB::table('users')->where('account_code', $code)->exists());

                DB::table('users')->where('id', $user->id)->update(['account_code' => $code]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('account_code', 32)->nullable(false)->change();
        });
    }
};
