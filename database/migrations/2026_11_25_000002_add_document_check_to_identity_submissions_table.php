<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_submissions', function (Blueprint $table) {
            $table->string('document_type', 32)->default('national_id');
            $table->string('verification_method', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('identity_submissions', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'verification_method']);
        });
    }
};
