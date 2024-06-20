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
        Schema::create('pksanc__pokedex_marking', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->integer('pokedex_id');
            $table->integer('form_index');
            $table->string('marking');
            $table->foreignUuid('user_uuid');
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pksanc__pokedex_marking');
    }
};
