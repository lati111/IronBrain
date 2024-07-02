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
        Schema::create('compendium__action', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('name', 64);
            $table->integer('type')->default(1);
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('compendium__resource', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('code')->unique();
            $table->string('name', 64);
            $table->integer('recharge_interval')->default(0);
            $table->text('recharge_formula')->nullable();
            $table->timestamps();
        });

        Schema::create('compendium__action_cost', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('action_uuid');
            $table->foreignUuid('resource_uuid');
            $table->text('cost_formula');
            $table->timestamps();

            $table->foreign('action_uuid', 'action_cost_link_1')->references('uuid')->on('compendium__action')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('resource_uuid', 'action_cost_link_2')->references('uuid')->on('compendium__resource')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__resistance_modifier', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('element', 32);
            $table->integer('stage')->default(0);
            $table->boolean('is_base')->default(false);
            $table->timestamps();
        });

        //| Creature template parts
        Schema::create('compendium__creature_template', function (Blueprint $table) {
            $table->string('code', 64)->primary();
            $table->string('name', 64);
            $table->timestamps();
        });

        Schema::create('compendium__creature_template_resistance_modifier', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('resistance_modifier_uuid');
            $table->foreignUuid('creature_template');
            $table->timestamps();

            $table->foreign('resistance_modifier_uuid', 'template_resistance_link_1')->references('uuid')->on('compendium__resistance_modifier')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('creature_template', 'template_resistance_link_2')->references('code')->on('compendium__creature_template')->onDelete('cascade')->onUpdate('no action');
        });

        //| Trait parts
        Schema::create('compendium__trait', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('name', 64);
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('compendium__stat_trait', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('trait_uuid');
            $table->string('stat');
            $table->text('formula');
            $table->timestamps();

            $table->foreign('trait_uuid', 'stat_trait_link')->references('uuid')->on('compendium__trait')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__action_trait', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('trait_uuid');
            $table->foreignUuid('action_uuid');
            $table->timestamps();

            $table->foreign('trait_uuid', 'action_trait_link_1')->references('uuid')->on('compendium__trait')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('action_uuid', 'action_trait_link_2')->references('uuid')->on('compendium__action')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__resource_trait', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('trait_uuid');
            $table->foreignUuid('resource_uuid');
            $table->text('cap_formula');
            $table->timestamps();

            $table->foreign('trait_uuid', 'resource_trait_link_1')->references('uuid')->on('compendium__trait')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('resource_uuid', 'resource_trait_link_2')->references('uuid')->on('compendium__resource')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__proficiency_trait', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('trait_uuid');
            $table->string('skill');
            $table->integer('proficiency_level')->default(1);
            $table->timestamps();

            $table->foreign('trait_uuid', 'proficiency_trait_link')->references('uuid')->on('compendium__trait')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__roll_modifier_trait', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('trait_uuid');
            $table->string('roll_type');
            $table->text('formula');
            $table->timestamps();

            $table->foreign('trait_uuid', 'roll_modifier_trait_link')->references('uuid')->on('compendium__trait')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('compendium__resistance_modifier_trait', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('trait_uuid');
            $table->foreignUuid('resistance_modifier_uuid');
            $table->timestamps();

            $table->foreign('trait_uuid', 'resistance_modifier_trait_link_1')->references('uuid')->on('compendium__trait')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('resistance_modifier_uuid', 'resistance_modifier_trait_link_2')->references('uuid')->on('compendium__resistance_modifier')->onDelete('cascade')->onUpdate('no action');
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

        Schema::create('compendium__statblock_trait', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('statblock_uuid');
            $table->foreignUuid('trait_uuid');
            $table->timestamps();

            $table->foreign('statblock_uuid', 'statblock_trait_link_1')->references('uuid')->on('compendium__statblock')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('trait_uuid', 'statblock_trait_link_2')->references('uuid')->on('compendium__trait')->onDelete('cascade')->onUpdate('no action');
        });


        Schema::create('compendium__statblock_creature_template', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->foreignUuid('statblock_uuid');
            $table->foreignUuid('creature_template');
            $table->timestamps();

            $table->foreign('statblock_uuid', 'statblock_template_link_1')->references('uuid')->on('compendium__statblock')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('creature_template', 'statblock_template_link_2')->references('code')->on('compendium__creature_template')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //| Statblock parts
        Schema::dropIfExists('compendium__statblock_creature_template');
        Schema::dropIfExists('compendium__statblock_trait');
        Schema::dropIfExists('compendium__statblock');

        //| Creature template parts
        Schema::dropIfExists('compendium__creature_template_resistance_modifier');
        Schema::dropIfExists('compendium__creature_template');

        //| Trait parts
        Schema::dropIfExists('compendium__resistance_modifier_trait');
        Schema::dropIfExists('compendium__roll_modifier_trait');
        Schema::dropIfExists('compendium__proficiency_trait');
        Schema::dropIfExists('compendium__resource_trait');
        Schema::dropIfExists('compendium__action_trait');
        Schema::dropIfExists('compendium__stat_trait');
        Schema::dropIfExists('compendium__trait');

        //| Data parts
        Schema::dropIfExists('compendium__resistance_modifier');
        Schema::dropIfExists('compendium__action_cost');
        Schema::dropIfExists('compendium__resource');
        Schema::dropIfExists('compendium__action');
    }
};
