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
        Schema::create('role_permission', function (Blueprint $table) {
            $table->string('role', 128);
            $table->string('permission', 128);
            $table->timestamps();

            $table->foreign('role')->references('role')->on('role')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('permission')->references('permission')->on('permission')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permission');
    }
};
