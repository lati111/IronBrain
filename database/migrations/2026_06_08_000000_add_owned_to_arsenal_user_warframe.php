<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arsenal__user_warframe', function (Blueprint $table) {
            $table->unsignedTinyInteger('shards')->default(0)->after('forma');
        });
    }

    public function down(): void
    {
        Schema::table('arsenal__user_warframe', function (Blueprint $table) {
            $table->dropColumn('shards');
        });
    }
};
