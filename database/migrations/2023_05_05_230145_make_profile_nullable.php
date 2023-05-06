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
            $table->string('pronouns', 16)->default('unspecified')->change();
            $table->text('description')->nullable()->change();
            $table->string('profile_picture')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth__user', function (Blueprint $table) {
            $table->string('pronouns', 16)->change();
            $table->text('description')->change();
            $table->string('profile_picture')->change();
        });
    }
};
