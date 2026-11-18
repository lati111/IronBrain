<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arsenal__user_warframe', function (Blueprint $table) {
            $table->string('name', 255)->nullable()->after('id');
        });

        Schema::table('arsenal__user_weapon', function (Blueprint $table) {
            $table->string('name', 255)->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('arsenal__user_warframe', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('arsenal__user_weapon', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
