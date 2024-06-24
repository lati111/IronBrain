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
        //| Data parts
        Schema::create('compendium__resistance_modifier', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('element', 32);
            $table->integer('stage')->default(0);
            $table->boolean('is_base')->default(false);
            $table->timestamps();
        });

        //| Statblock parts
        Schema::create('compendium__statblock', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('name', 64);
            $table->string('alignment', 32)->nullable();
            $table->integer('base_ac')->default(10);
            $table->integer('base_hp')->default(1);
            $table->integer('temp_hp')->default(0);
            $table->integer('base_strength')->default(0);
            $table->integer('base_dexterity')->default(0);
            $table->integer('base_constitution')->default(0);
            $table->integer('base_intelligence')->default(0);
            $table->integer('base_wisdom')->default(0);
            $table->integer('base_charisma')->default(0);
            $table->integer('base_walk_speed')->default(30);
            $table->integer('base_swim_speed')->nullable();
            $table->integer('base_climb_speed')->nullable();
            $table->integer('base_fly_speed')->nullable();
            $table->integer('base_burrow_speed')->nullable();
            $table->timestamps();
        });

        Schema::create('compendium__creature_template', function (Blueprint $table) {
            $table->string('code', 64)->primary();
            $table->string('name', 64);
            $table->timestamps();
        });

        Schema::create('compendium__statblock_creature_template', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('statblock_uuid');
            $table->foreignUuid('creature_template');
            $table->timestamps();

            $table->foreign('statblock_uuid', 'statblock_template_link_1')->references('uuid')->on('compendium__statblock')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('creature_template', 'statblock_template_link_2')->references('code')->on('compendium__creature_template')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__creature_template_resistance_modifier', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('resistance_modifier_uuid');
            $table->foreignUuid('creature_template');
            $table->timestamps();

            $table->foreign('resistance_modifier_uuid', 'template_resistance_link_1')->references('uuid')->on('compendium__resistance_modifier')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('creature_template', 'template_resistance_link_2')->references('code')->on('compendium__creature_template')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //| Statblock parts
        Schema::dropIfExists('compendium__creature_template_resistance_modifier');
        Schema::dropIfExists('compendium__statblock_creature_template');
        Schema::dropIfExists('compendium__creature_template');
        Schema::dropIfExists('compendium__statblock');

        //| Data parts
        Schema::dropIfExists('compendium__resistance_modifier');
    }
};
