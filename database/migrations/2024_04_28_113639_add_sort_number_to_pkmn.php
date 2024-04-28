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
        Schema::table('pksanc__pokemon', function (Blueprint $table) {
            $table->integer('internal_pokedex_id')->after('pokedex_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pksanc__pokemon', function (Blueprint $table) {
            $table->dropColumn('internal_pokedex_id');
        });
    }
};
