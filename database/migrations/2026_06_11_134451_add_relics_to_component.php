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
        Schema::create('arsenal__relic', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('component_uuid');
            $table->string('name', 12);
            $table->string('grade', 8);
            $table->string('key', 4);
            $table->string('rarity', 16);
            $table->timestamps();

            $table->foreign('component_uuid')->references('uuid')->on('arsenal__component')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsenal__relic');
    }
};
