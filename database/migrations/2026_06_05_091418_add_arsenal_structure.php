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
        //| Warframe
        Schema::create('arsenal__warframe', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('name', 64);
            $table->text('description');
            $table->text('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('arsenal__user_warframe', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('owner_uuid');
            $table->string('id', 255);
            $table->boolean('potato')->default(false);
            $table->boolean('exilus')->default(false);
            $table->boolean('fashioned')->default(false);
            $table->boolean('built')->default(false);
            $table->integer('forma')->default(0);
            $table->string('school')->nullable();
            $table->timestamps();

            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('id')->references('id')->on('arsenal__warframe')->onDelete('cascade')->onUpdate('no action');
        });

        //| Weapon
        Schema::create('arsenal__weapon', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('type', 64);
            $table->string('weapon_type', 64);
            $table->string('exalted_id', 255)->nullable();

            $table->string('name', 64);
            $table->text('description');
            $table->text('icon')->nullable();
            $table->timestamps();

            $table->foreign('exalted_id')->references('id')->on('arsenal__warframe')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('arsenal__user_weapon', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('owner_uuid');
            $table->string('id', 255);

            $table->boolean('riven')->default(false);
            $table->boolean('potato')->default(false);
            $table->boolean('exilus')->default(false);
            $table->boolean('built')->default(false);
            $table->integer('forma')->default(0);
            $table->string('school')->nullable();

            $table->timestamps();

            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('id')->references('id')->on('arsenal__weapon')->onDelete('cascade')->onUpdate('no action');
        });

        //| Companion
        Schema::create('arsenal__companion', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('type', 64);
            $table->string('pet_type', 64);

            $table->string('name', 64);
            $table->text('description');
            $table->text('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('arsenal__user_companion', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('owner_uuid');
            $table->string('id', 255);

            $table->string('name', 255)->nullable();
            $table->boolean('fashioned')->default(false);
            $table->boolean('potato')->default(false);
            $table->boolean('built')->default(false);
            $table->integer('forma')->default(0);
            $table->string('school')->nullable();

            $table->timestamps();

            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('id')->references('id')->on('arsenal__companion')->onDelete('cascade')->onUpdate('no action');
        });

        //| Component
        Schema::create('arsenal__component', function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->string('blueprint_id', 255);
            $table->string('type', 64);

            $table->string('name', 128);
            $table->text('icon')->nullable();
            $table->integer('amount')->default(1);
            $table->timestamps();
        });

        Schema::create('arsenal__user_component', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('owner_uuid');
            $table->string('id', 255);
            $table->integer('amount')->default(1);
            $table->timestamps();

            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('id')->references('id')->on('arsenal__component')->onDelete('cascade')->onUpdate('no action');
        });

        //| Loadout
        Schema::create('arsenal__loadout', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('owner_uuid');

            $table->string('name', 32);
            $table->foreignUuid('warframe_uuid');
            $table->foreignUuid('primary_uuid')->nullable();
            $table->foreignUuid('secondary_uuid')->nullable();
            $table->foreignUuid('melee_uuid')->nullable();
            $table->foreignUuid('companion_uuid')->nullable();
            $table->foreignUuid('companion_weapon_uuid')->nullable();
            $table->timestamps();

            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('warframe_uuid')->references('uuid')->on('arsenal__user_warframe')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('primary_uuid')->references('uuid')->on('arsenal__user_weapon')->nullOnDelete()->onUpdate('no action');
            $table->foreign('secondary_uuid')->references('uuid')->on('arsenal__user_weapon')->nullOnDelete()->onUpdate('no action');
            $table->foreign('melee_uuid')->references('uuid')->on('arsenal__user_weapon')->nullOnDelete()->onUpdate('no action');
            $table->foreign('companion_uuid')->references('uuid')->on('arsenal__user_companion')->nullOnDelete()->onUpdate('no action');
            $table->foreign('companion_weapon_uuid')->references('uuid')->on('arsenal__user_weapon')->nullOnDelete()->onUpdate('no action');
        });

        Schema::create('arsenal__loadout_exalted', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('loadout_uuid');
            $table->foreignUuid('weapon_uuid');
            $table->timestamps();

            $table->foreign('loadout_uuid')->references('uuid')->on('arsenal__loadout')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('weapon_uuid')->references('uuid')->on('arsenal__user_weapon')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsenal__loadout_exalted');
        Schema::dropIfExists('arsenal__loadout');
        Schema::dropIfExists('arsenal__user_component');
        Schema::dropIfExists('arsenal__component');
        Schema::dropIfExists('arsenal__user_companion');
        Schema::dropIfExists('arsenal__companion');
        Schema::dropIfExists('arsenal__user_weapon');
        Schema::dropIfExists('arsenal__weapon');
        Schema::dropIfExists('arsenal__user_warframe');
        Schema::dropIfExists('arsenal__warframe');
    }
};
