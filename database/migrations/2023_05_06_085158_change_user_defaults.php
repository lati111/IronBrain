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
        Schema::table('auth__user', function (Blueprint $table) {
            $table->string('pronouns', 16)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth__user', function (Blueprint $table) {
            $table->string('pronouns', 16)->default('unspecified')->change();
        });
    }
};
