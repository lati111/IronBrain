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
        Schema::create('nav_submenu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projectId');
            $table->string('name', 64);
            $table->integer('order');
            $table->string('route')->nullable();
            $table->string('permission')->nullable();
            $table->timestamps();

            $table->foreign('projectId')->references('id')->on('project')->onUpdate('no action')->onDelete('restrict');
            $table->foreign('permission')->references('permission')->on('permission')->onUpdate('no action')->onDelete('restrict');
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