<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pksanc__type', function (Blueprint $table) {
            $table->string('type', 32)->primary();
            $table->string('name', 32);
            $table->timestamps();
        });

        Schema::create('pksanc__game', function (Blueprint $table) {
            $table->string('game', 255)->primary();
            $table->string('name', 255);
            $table->string('original_game', 255)->nullable();
            $table->boolean('is_romhack')->default(false);
            $table->timestamps();
        });

        Schema::create('pksanc__nature', function (Blueprint $table) {
            $table->string('nature', 255)->primary();
            $table->string('name', 255);
            $table->decimal('atk_modifier')->default(1.0);
            $table->decimal('def_modifier')->default(1.0);
            $table->decimal('spa_modifier')->default(1.0);
            $table->decimal('spd_modifier')->default(1.0);
            $table->decimal('spe_modifier')->default(1.0);
            $table->timestamps();
        });

        Schema::create('pksanc__import_csv', function (Blueprint $table) {
            $table->uuid()->primary();
            $table->string('csv', 255);
            $table->string('game', 255);
            $table->string('name', 255);
            $table->decimal('version', 11, 2);
            $table->boolean('validated')->default(false);
            $table->foreignUuid('uploader_uuid');
            $table->timestamps();

            $table->foreign('game')->references('game')->on('pksanc__game')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('uploader_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('pksanc__pokeball', function (Blueprint $table) {
            $table->string('pokeball', 32)->primary();
            $table->string('name', 32);
            $table->string('sprite', 255);
            $table->timestamps();
        });

        Schema::create('pksanc__ribbon', function (Blueprint $table) {
            $table->string('ribbon', 32)->primary();
            $table->string('name', 32);
            $table->string('sprite', 255);
            $table->timestamps();
        });

        Schema::create('pksanc__ability', function (Blueprint $table) {
            $table->string('ability', 255)->primary();
            $table->string('name', 255);
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('pksanc__move', function (Blueprint $table) {
            $table->string('alias', 255)->nullable();
            $table->string('move', 255)->primary();
            $table->string('name', 255);
            $table->integer('power')->nullable();
            $table->integer('accuracy')->nullable();
            $table->integer('priority')->default(0);
            $table->text('description');
            $table->string('type', 32);
            $table->string('move_type', 32);
            $table->timestamps();

            $table->foreign('type')->references('type')->on('pksanc__type')->onDelete('restrict')->onUpdate('no action');
        });

        Schema::create('pksanc__trainer', function (Blueprint $table) {
            $table->integer('trainer_id');
            $table->integer('secret_id');
            $table->uuid('uuid')->primary();
            $table->string('name', 32);
            $table->string('gender', 1);
            $table->string('game', 255);
            $table->foreignUuid('owner_uuid');
            $table->timestamps();

            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('game')->references('game')->on('pksanc__game')->onDelete('restrict')->onUpdate('no action');
        });

        Schema::create('pksanc__pokemon', function (Blueprint $table) {
            $table->string('species', 255);
            $table->string('species_name', 255);
            $table->string('pokemon', 255)->primary();
            $table->string('form', 255)->nullable();
            $table->string('form_name', 255)->nullable();
            $table->integer('form_index')->default(0);
            $table->integer('pokedex_id');
            $table->string('primary_type', 32);
            $table->string('secondary_type', 32);
            $table->integer('base_hp');
            $table->integer('base_atk');
            $table->integer('base_def');
            $table->integer('base_spa');
            $table->integer('base_spd');
            $table->integer('base_spe');
            $table->string('sprite', 255)->nullable();
            $table->string('sprite_shiny', 255)->nullable();
            $table->string('sprite_female', 255)->nullable();
            $table->string('sprite_female_shiny', 255)->nullable();
            $table->integer('generation');
            $table->string('pokemon_type', 32);
            $table->timestamps();

            $table->foreign('primary_type')->references('type')->on('pksanc__type')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('secondary_type')->references('type')->on('pksanc__type')->onDelete('restrict')->onUpdate('no action');
        });

        Schema::create('pksanc__stored_pokemon', function (Blueprint $table) {
            $table->string('PID', 12);
            $table->uuid('uuid')->primary();
            $table->string('nickname', 14);
            $table->string('pokemon', 255);
            $table->string('gender', 1);
            $table->string('nature', 255);
            $table->string('ability', 255);
            $table->string('pokeball', 64);
            $table->string('hidden_power_type', 32);
            $table->string('tera_type', 32);
            $table->integer('friendship');
            $table->integer('level');
            $table->integer('height');
            $table->integer('weight');
            $table->boolean('is_shiny')->default(false);
            $table->boolean('is_alpha')->default(false);
            $table->boolean('has_n_sparkle')->default(false);
            $table->boolean('can_gigantamax')->default(false);
            $table->integer('dynamax_level')->default(0);
            $table->uuid('csv_uuid');
            $table->integer('csv_line');
            $table->foreignUuid('owner_uuid');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->foreign('pokemon')->references('pokemon')->on('pksanc__pokemon')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('nature')->references('nature')->on('pksanc__nature')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('ability')->references('ability')->on('pksanc__ability')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('pokeball')->references('pokeball')->on('pksanc__pokeball')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('hidden_power_type')->references('type')->on('pksanc__type')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('tera_type')->references('type')->on('pksanc__type')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('csv_uuid')->references('uuid')->on('pksanc__import_csv')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('pksanc__staged_pokemon', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('new_pokemon_uuid');
            $table->foreignUuid('old_pokemon_uuid')->nullable();
            $table->timestamps();

            $table->foreign('new_pokemon_uuid')->references('uuid')->on('pksanc__stored_pokemon')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('old_pokemon_uuid')->references('uuid')->on('pksanc__stored_pokemon')->onDelete('set null')->onUpdate('no action');
        });

        Schema::create('pksanc__stats', function (Blueprint $table) {
            $table->foreignUuid('pokemon_uuid')->primary();
            $table->integer('hp_iv');
            $table->integer('hp_ev');
            $table->integer('atk_iv');
            $table->integer('atk_ev');
            $table->integer('def_iv');
            $table->integer('def_ev');
            $table->integer('spa_iv');
            $table->integer('spa_ev');
            $table->integer('spd_iv');
            $table->integer('spd_ev');
            $table->integer('spe_iv');
            $table->integer('spe_ev');
            $table->timestamps();

            $table->foreign('pokemon_uuid')->references('uuid')->on('pksanc__stored_pokemon')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('pksanc__contest_stats', function (Blueprint $table) {
            $table->foreignUuid('pokemon_uuid')->primary();
            $table->integer('beauty');
            $table->integer('cool');
            $table->integer('cute');
            $table->integer('smart');
            $table->integer('tough');
            $table->integer('sheen');
            $table->timestamps();

            $table->foreign('pokemon_uuid')->references('uuid')->on('pksanc__stored_pokemon')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('pksanc__moveset', function (Blueprint $table) {
            $table->foreignUuid('pokemon_uuid')->primary();
            $table->string('move1', 255)->nullable();
            $table->integer('move1_pp_up')->nullable();
            $table->string('move2', 255)->nullable();
            $table->integer('move2_pp_up')->nullable();
            $table->string('move3', 255)->nullable();
            $table->integer('move3_pp_up')->nullable();
            $table->string('move4', 255)->nullable();
            $table->integer('move4_pp_up')->nullable();
            $table->timestamps();

            $table->foreign('pokemon_uuid')->references('uuid')->on('pksanc__stored_pokemon')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('move1')->references('move')->on('pksanc__move')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('move2')->references('move')->on('pksanc__move')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('move3')->references('move')->on('pksanc__move')->onDelete('restrict')->onUpdate('no action');
            $table->foreign('move4')->references('move')->on('pksanc__move')->onDelete('restrict')->onUpdate('no action');
        });

        Schema::create('pksanc__pokemon_ribbons', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('pokemon_uuid');
            $table->string('ribbon', 255);
            $table->timestamps();

            $table->foreign('pokemon_uuid')->references('uuid')->on('pksanc__stored_pokemon')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('ribbon')->references('ribbon')->on('pksanc__ribbon')->onDelete('restrict')->onUpdate('no action');
        });

        Schema::create('pksanc__origin', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('pokemon_uuid');
            $table->foreignUuid('trainer_uuid');
            $table->string('game', 255);
            $table->date('met_date');
            $table->string('met_location', 255)->nullable();
            $table->integer('met_level');
            $table->boolean('was_egg');
            $table->timestamps();

            $table->foreign('pokemon_uuid')->references('uuid')->on('pksanc__stored_pokemon')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('trainer_uuid')->references('uuid')->on('pksanc__trainer')->onDelete('restrict')->onUpdate('no action');

            $table->foreign('game')->references('game')->on('pksanc__game')->onDelete('restrict')->onUpdate('no action');
        });
    }

    public function down(): void
    {
        schema::drop('pksanc__origin');
        schema::drop('pksanc__pokemon_ribbons');
        schema::drop('pksanc__moveset');
        schema::drop('pksanc__contest_stats');
        schema::drop('pksanc__stats');
        schema::drop('pksanc__stored_pokemon');
        schema::drop('pksanc__pokemon');
        schema::drop('pksanc__trainer');
        schema::drop('pksanc__move');
        schema::drop('pksanc__ability');
        schema::drop('pksanc__ribbon');
        schema::drop('pksanc__pokeball');
        schema::drop('pksanc__import_csv');
        schema::drop('pksanc__nature');
        schema::drop('pksanc__game');
        schema::drop('pksanc__type');
    }
};
