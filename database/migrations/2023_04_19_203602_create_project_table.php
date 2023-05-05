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
        Schema::create('project', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->text('description');
            $table->string('thumbnail')->nullable();
            $table->string('route')->nullable();
            $table->string('permission')->nullable();
            $table->integer('order')->nullable()->unique();
            $table->boolean('inOverview')->default(true);
            $table->boolean('inNav')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('permission')->references('permission')->on('permission')->onUpdate('no action')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project');
    }
};
