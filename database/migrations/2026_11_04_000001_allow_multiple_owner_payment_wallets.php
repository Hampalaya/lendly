<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep an index for the foreign key before removing MySQL's unique index.
        Schema::table('owner_payment_settings', function (Blueprint $table) {
            $table->index('user_id');
        });
        Schema::table('owner_payment_settings', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('owner_payment_settings')->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Remove additional wallets before restoring the single-wallet restriction.');
        }

        Schema::table('owner_payment_settings', function (Blueprint $table) {
            $table->unique('user_id');
        });
        Schema::table('owner_payment_settings', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
