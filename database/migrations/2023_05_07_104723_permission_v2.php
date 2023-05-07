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
        Schema::table('auth__role', function (Blueprint $table) {
            $table->boolean('isAdmin')->default(false);
        });

        Schema::table('auth__permission', function (Blueprint $table) {
            $table->string('permission', 128)->unique()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auth__role', function (Blueprint $table) {
            $table->dropColumn('isAdmin');
        });

        Schema::table('auth__permission', function (Blueprint $table) {
            $table->dropColumn('permission');
        });
    }
};
