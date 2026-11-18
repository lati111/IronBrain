<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The compendium module's tables: actions and resources, the effects an action can trigger,
     * creature templates, traits and the statblocks they are applied to
     */
    public function up(): void
    {
        //| Data parts
        Schema::create('dnd__compendium_action', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name', 64);
            // 0=free action, 1=action, 2=bonus action, 3=reaction, 4=legendary action
            $table->integer('type')->default(1);
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('dnd__compendium_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->timestamps();
        });

        Schema::create('dnd__compendium_resource', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('code')->unique();
            $table->string('name', 64);
            // 0=not, 1=short rest, 2=long rest, 3=dawn
            $table->integer('recharge_interval')->default(0);
            // When null the resource recharges to full
            $table->text('recharge_formula')->nullable();
            $table->timestamps();
        });

        Schema::create('dnd__compendium_action_cost', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('action_uuid');
            $table->foreignUuid('resource_uuid');
            $table->text('cost_formula');
            $table->timestamps();

            $this->addLink($table, 'action_cost_link_1', 'action_uuid', 'action');
            $this->addLink($table, 'action_cost_link_2', 'resource_uuid', 'resource');
        });

        Schema::create('dnd__compendium_resistance_modifier', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('element', 32);
            $table->integer('stage')->default(0);
            // Whether the modifier is the base value of the creature, applied before the other modifiers
            $table->boolean('is_base')->default(false);
            $table->timestamps();
        });

        //| Action logic
        Schema::create('dnd__compendium_action_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('action_uuid');
            $table->foreignUuid('effect_uuid');
            $table->timestamps();

            $this->addLink($table, 'action_effect_link_1', 'action_uuid', 'action');
            $this->addLink($table, 'action_effect_link_2', 'effect_uuid', 'effect');
        });

        Schema::create('dnd__compendium_attack', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name', 64);
            // The formula determining the to hit of the attack
            $table->text('formula');
            // A comma separated list
            $table->text('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('dnd__compendium_attack_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('effect_uuid');
            $table->foreignUuid('attack_uuid');
            $table->timestamps();

            $this->addLink($table, 'attack_effect_link_1', 'effect_uuid', 'effect');
            $this->addLink($table, 'attack_effect_link_2', 'attack_uuid', 'attack');
        });

        Schema::create('dnd__compendium_attack_result_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('attack_uuid');
            $table->foreignUuid('effect_uuid');
            // 0=miss, 1=hit, 2=crit
            $table->integer('result_type');
            $table->timestamps();

            $this->addLink($table, 'attack_result_effect_link_1', 'attack_uuid', 'attack');
            $this->addLink($table, 'attack_result_effect_link_2', 'effect_uuid', 'effect');
        });

        Schema::create('dnd__compendium_saving_throw', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name', 64);
            $table->text('dc_formula');
            // The stat used in the saving throw
            $table->string('stat');
            // A comma separated list
            $table->text('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('dnd__compendium_saving_throw_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('effect_uuid');
            $table->foreignUuid('saving_throw_uuid');
            $table->timestamps();

            $this->addLink($table, 'saving_throw_effect_link_1', 'effect_uuid', 'effect');
            $this->addLink($table, 'saving_throw_effect_link_2', 'saving_throw_uuid', 'saving_throw');
        });

        Schema::create('dnd__compendium_on_saving_throw_result_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('saving_throw_uuid');
            $table->foreignUuid('effect_uuid');
            // 0=failure, 1=success
            $table->integer('result_type');
            $table->timestamps();

            $this->addLink($table, 'saving_throw_result_effect_link_1', 'saving_throw_uuid', 'saving_throw');
            $this->addLink($table, 'saving_throw_result_effect_link_2', 'effect_uuid', 'effect');
        });

        Schema::create('dnd__compendium_opposed_check', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->text('initiator_check_formula');
            $table->text('opposer_check_formula');
            // A comma separated list
            $table->text('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('dnd__compendium_opposed_check_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('effect_uuid');
            $table->foreignUuid('opposed_check_uuid');
            $table->timestamps();

            $this->addLink($table, 'opposed_check_effect_link_1', 'effect_uuid', 'effect');
            $this->addLink($table, 'opposed_check_effect_link_2', 'opposed_check_uuid', 'opposed_check');
        });

        Schema::create('dnd__compendium_opposed_check_result_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('opposed_check_uuid');
            $table->foreignUuid('effect_uuid');
            // 0=failure, 1=success
            $table->integer('result_type');
            $table->timestamps();

            $this->addLink($table, 'opposed_check_result_effect_link_1', 'opposed_check_uuid', 'opposed_check');
            $this->addLink($table, 'opposed_check_result_effect_link_2', 'effect_uuid', 'effect');
        });

        Schema::create('dnd__compendium_check', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->text('check');
            // A comma separated list
            $table->text('tags')->nullable();
            $table->timestamps();
        });

        Schema::create('dnd__compendium_check_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('effect_uuid');
            $table->foreignUuid('check_uuid');
            $table->timestamps();

            $this->addLink($table, 'check_effect_link_1', 'effect_uuid', 'effect');
            $this->addLink($table, 'check_effect_link_2', 'check_uuid', 'check');
        });

        Schema::create('dnd__compendium_check_result_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('check_uuid');
            $table->foreignUuid('effect_uuid');
            // 0=failure, 1=success
            $table->integer('result_type');
            $table->timestamps();

            $this->addLink($table, 'check_result_effect_link_1', 'check_uuid', 'check');
            $this->addLink($table, 'check_result_effect_link_2', 'effect_uuid', 'effect');
        });

        //| Effects
        Schema::create('dnd__compendium_damage_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('effect_uuid');
            $table->text('formula');
            // When the user should choose the element, the options are separated by a comma
            $table->string('element');
            $table->timestamps();

            $this->addLink($table, 'damage_effect_link', 'effect_uuid', 'effect');
        });

        Schema::create('dnd__compendium_heal_effect', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('effect_uuid');
            $table->text('formula');
            $table->timestamps();

            $this->addLink($table, 'heal_effect_link', 'effect_uuid', 'effect');
        });

        //| Creature template parts
        Schema::create('dnd__compendium_creature_template', function (Blueprint $table) {
            $table->string('code', 64)->primary();
            $table->string('name', 64);
            $table->timestamps();
        });

        Schema::create('dnd__compendium_creature_template_resistance_modifier', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('creature_template', 64);
            $table->foreignUuid('resistance_modifier_uuid');
            $table->timestamps();

            $this->addLink($table, 'template_resistance_link_1', 'creature_template', 'creature_template', 'code');
            $this->addLink($table, 'template_resistance_link_2', 'resistance_modifier_uuid', 'resistance_modifier');
        });

        //| Trait parts
        Schema::create('dnd__compendium_trait', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name', 64);
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('dnd__compendium_stat_trait', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('trait_uuid');
            $table->string('stat');
            $table->text('formula');
            $table->timestamps();

            $this->addLink($table, 'stat_trait_link', 'trait_uuid', 'trait');
        });

        Schema::create('dnd__compendium_action_trait', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('trait_uuid');
            $table->foreignUuid('action_uuid');
            $table->timestamps();

            $this->addLink($table, 'action_trait_link_1', 'trait_uuid', 'trait');
            $this->addLink($table, 'action_trait_link_2', 'action_uuid', 'action');
        });

        Schema::create('dnd__compendium_resource_trait', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('trait_uuid');
            $table->foreignUuid('resource_uuid');
            $table->text('cap_formula');
            $table->timestamps();

            $this->addLink($table, 'resource_trait_link_1', 'trait_uuid', 'trait');
            $this->addLink($table, 'resource_trait_link_2', 'resource_uuid', 'resource');
        });

        Schema::create('dnd__compendium_proficiency_trait', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('trait_uuid');
            $table->string('skill');
            // 0.5 for half proficient, 1 for fully proficient and 2 for expertise
            $table->double('proficiency_level')->default(1);
            $table->timestamps();

            $this->addLink($table, 'proficiency_trait_link', 'trait_uuid', 'trait');
        });

        Schema::create('dnd__compendium_roll_modifier_trait', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('trait_uuid');
            $table->string('roll_type');
            $table->text('formula');
            $table->timestamps();

            $this->addLink($table, 'roll_modifier_trait_link', 'trait_uuid', 'trait');
        });

        Schema::create('dnd__compendium_resistance_modifier_trait', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('trait_uuid');
            $table->foreignUuid('resistance_modifier_uuid');
            $table->timestamps();

            $this->addLink($table, 'resistance_modifier_trait_link_1', 'trait_uuid', 'trait');
            $this->addLink($table, 'resistance_modifier_trait_link_2', 'resistance_modifier_uuid', 'resistance_modifier');
        });

        //| Statblock parts
        Schema::create('dnd__compendium_statblock', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('name', 64);
            $table->string('alignment', 32)->nullable();
            // The size before any modifications, where 0 is medium
            $table->integer('base_size')->default(0);
            $table->integer('base_ac')->default(10);
            $table->integer('base_hp')->default(1);
            $table->integer('temp_hp')->default(0);
            $table->integer('base_strength')->default(10);
            $table->integer('base_dexterity')->default(10);
            $table->integer('base_constitution')->default(10);
            $table->integer('base_intelligence')->default(10);
            $table->integer('base_wisdom')->default(10);
            $table->integer('base_charisma')->default(10);
            $table->integer('base_walk_speed')->default(30);
            $table->integer('base_swim_speed')->nullable();
            $table->integer('base_climb_speed')->nullable();
            $table->integer('base_fly_speed')->nullable();
            $table->integer('base_burrow_speed')->nullable();
            $table->timestamps();
        });

        Schema::create('dnd__compendium_statblock_trait', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('statblock_uuid');
            $table->foreignUuid('trait_uuid');
            $table->timestamps();

            $this->addLink($table, 'statblock_trait_link_1', 'statblock_uuid', 'statblock');
            $this->addLink($table, 'statblock_trait_link_2', 'trait_uuid', 'trait');
        });

        Schema::create('dnd__compendium_statblock_creature_template', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('statblock_uuid');
            $table->string('creature_template', 64);
            $table->timestamps();

            $this->addLink($table, 'statblock_template_link_1', 'statblock_uuid', 'statblock');
            $this->addLink($table, 'statblock_template_link_2', 'creature_template', 'creature_template', 'code');
        });
    }

    /**
     * Adds a cascading foreign key to another compendium table. The key is named explicitly, as the generated names
     * exceed the maximum identifier length
     * @param Blueprint $table The table to add the key to
     * @param string $name The name of the foreign key
     * @param string $column The column holding the key
     * @param string $reference The name of the referenced table, without the compendium prefix
     * @param string $referenceColumn The referenced column
     */
    private function addLink(Blueprint $table, string $name, string $column, string $reference, string $referenceColumn = 'uuid'): void
    {
        $table->foreign($column, $name)
            ->references($referenceColumn)
            ->on('dnd__compendium_' . $reference)
            ->onDelete('cascade')
            ->onUpdate('no action');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //| Statblock parts
        Schema::dropIfExists('dnd__compendium_statblock_creature_template');
        Schema::dropIfExists('dnd__compendium_statblock_trait');
        Schema::dropIfExists('dnd__compendium_statblock');

        //| Trait parts
        Schema::dropIfExists('dnd__compendium_resistance_modifier_trait');
        Schema::dropIfExists('dnd__compendium_roll_modifier_trait');
        Schema::dropIfExists('dnd__compendium_proficiency_trait');
        Schema::dropIfExists('dnd__compendium_resource_trait');
        Schema::dropIfExists('dnd__compendium_action_trait');
        Schema::dropIfExists('dnd__compendium_stat_trait');
        Schema::dropIfExists('dnd__compendium_trait');

        //| Creature template parts
        Schema::dropIfExists('dnd__compendium_creature_template_resistance_modifier');
        Schema::dropIfExists('dnd__compendium_creature_template');

        //| Effects
        Schema::dropIfExists('dnd__compendium_heal_effect');
        Schema::dropIfExists('dnd__compendium_damage_effect');

        //| Action logic
        Schema::dropIfExists('dnd__compendium_check_result_effect');
        Schema::dropIfExists('dnd__compendium_check_effect');
        Schema::dropIfExists('dnd__compendium_check');
        Schema::dropIfExists('dnd__compendium_opposed_check_result_effect');
        Schema::dropIfExists('dnd__compendium_opposed_check_effect');
        Schema::dropIfExists('dnd__compendium_opposed_check');
        Schema::dropIfExists('dnd__compendium_on_saving_throw_result_effect');
        Schema::dropIfExists('dnd__compendium_saving_throw_effect');
        Schema::dropIfExists('dnd__compendium_saving_throw');
        Schema::dropIfExists('dnd__compendium_attack_result_effect');
        Schema::dropIfExists('dnd__compendium_attack_effect');
        Schema::dropIfExists('dnd__compendium_attack');
        Schema::dropIfExists('dnd__compendium_action_effect');

        //| Data parts
        Schema::dropIfExists('dnd__compendium_resistance_modifier');
        Schema::dropIfExists('dnd__compendium_action_cost');
        Schema::dropIfExists('dnd__compendium_resource');
        Schema::dropIfExists('dnd__compendium_effect');
        Schema::dropIfExists('dnd__compendium_action');
    }
};
