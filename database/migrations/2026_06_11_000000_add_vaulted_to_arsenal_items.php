<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arsenal__warframe', function (Blueprint $table) {
            $table->boolean('vaulted')->default(false)->after('prime');
        });

        Schema::table('arsenal__weapon', function (Blueprint $table) {
            $table->boolean('vaulted')->default(false)->after('prime');
        });
    }

    public function down(): void
    {
        Schema::table('arsenal__warframe', function (Blueprint $table) {
            $table->dropColumn('vaulted');
        });

        Schema::table('arsenal__weapon', function (Blueprint $table) {
            $table->dropColumn('vaulted');
        });
    }
};
