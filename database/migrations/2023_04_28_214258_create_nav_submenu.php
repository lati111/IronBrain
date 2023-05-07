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
        Schema::create('nav__submenu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id');
            $table->string('name', 64);
            $table->integer('order');
            $table->string('route')->nullable();
            $table->foreignId('permission_id')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('nav__project')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('permission_id')->references('id')->on('auth__permission')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nav');
        Schema::dropIfExists('nav_submenu');
    }
};
