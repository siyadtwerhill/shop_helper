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
        if (! Schema::hasColumn('users', 'account_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('account_code', 32)->nullable()->unique();
            });
        }

        DB::table('users')->whereNull('account_code')->select('id')->orderBy('id')->chunkById(500, function ($users): void {
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

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['account_code']);
            $table->dropColumn('account_code');
        });
    }
};
