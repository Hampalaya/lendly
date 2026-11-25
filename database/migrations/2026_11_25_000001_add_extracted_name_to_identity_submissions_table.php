<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_submissions', function (Blueprint $table) {
            $table->text('extracted_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('identity_submissions', function (Blueprint $table) {
            $table->dropColumn('extracted_name');
        });
    }
};
