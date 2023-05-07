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
        Schema::create('auth__user_role', function (Blueprint $table) {
            $table->foreignUuid('user_uuid');
            $table->foreignId('role_id');
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('auth__user')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('role_id')->references('id')->on('auth__role')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role');
    }
};
