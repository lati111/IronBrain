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
        //| Restaurant
        Schema::create('dnd__restaurant_restaurant', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('owner_uuid');
            $table->string('name', 64);

            $table->integer('bits')->default(0);
            $table->integer('copper')->default(0);
            $table->integer('silver')->default(0);
            $table->integer('gold')->default(0);

            $table->timestamps();

            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_restaurant_user', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->foreignUuid('user_uuid');
            $table->timestamps();

            $table->unique(['restaurant_uuid', 'user_uuid']);
            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('user_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
        });

        //| Recipe
        Schema::create('dnd__restaurant_recipe', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');

            $table->string('name', 64);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);

            $table->integer('prep_time')->default(0);
            $table->integer('cooking_time')->default(0);
            $table->integer('fresh_for')->default(0);
            $table->integer('good_for')->default(0);
            $table->integer('expires_in')->default(0);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        //| Ingredient
        Schema::create('dnd__restaurant_ingredient', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');

            $table->string('name', 64);
            $table->text('icon')->nullable();
            $table->integer('expires_in')->default(0);
            $table->integer('size')->default(1);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_recipe_ingredient', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('recipe_uuid');
            $table->foreignUuid('ingredient_uuid');
            $table->integer('amount')->default(1);
            $table->timestamps();

            // Explicit index names, as the generated ones exceed MySQL's 64 character limit
            $table->unique(['recipe_uuid', 'ingredient_uuid'], 'dnd__restaurant_recipe_ingredient_unique');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('ingredient_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
        });

        //| Equipment
        Schema::create('dnd__restaurant_equipment', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->foreignUuid('parent_uuid')->nullable();

            $table->string('name', 64);
            $table->text('description')->nullable();
            $table->string('category', 64);
            $table->integer('cleaning_time')->default(0);
            $table->decimal('multiplier', 8, 2)->default(1.0);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        // Added separately, as the self-reference requires the table to exist first
        Schema::table('dnd__restaurant_equipment', function (Blueprint $table) {
            $table->foreign('parent_uuid')->references('uuid')->on('dnd__restaurant_equipment')->nullOnDelete()->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_recipe_equipment', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('recipe_uuid');
            $table->foreignUuid('equipment_uuid');
            $table->integer('amount')->default(1);
            $table->timestamps();

            $table->unique(['recipe_uuid', 'equipment_uuid'], 'dnd__restaurant_recipe_equipment_unique');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('equipment_uuid')->references('uuid')->on('dnd__restaurant_equipment')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_owned_equipment', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->foreignUuid('equipment_uuid');
            $table->integer('amount')->default(1);
            $table->timestamps();

            $table->unique(['restaurant_uuid', 'equipment_uuid'], 'dnd__restaurant_owned_equipment_unique');
            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('equipment_uuid')->references('uuid')->on('dnd__restaurant_equipment')->onDelete('cascade')->onUpdate('no action');
        });

        //| Storage
        Schema::create('dnd__restaurant_storage', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');

            $table->string('name', 64);
            $table->integer('capacity')->default(0);
            $table->decimal('expiration_multiplier', 8, 2)->default(1.0);
            $table->text('background_image')->nullable();

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_storage_ingredient', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('storage_uuid');
            $table->foreignUuid('ingredient_uuid');
            $table->integer('amount')->default(1);
            $table->timestamps();

            $table->unique(['storage_uuid', 'ingredient_uuid'], 'dnd__restaurant_storage_ingredient_unique');
            $table->foreign('storage_uuid')->references('uuid')->on('dnd__restaurant_storage')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('ingredient_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dnd__restaurant_storage_ingredient');
        Schema::dropIfExists('dnd__restaurant_storage');
        Schema::dropIfExists('dnd__restaurant_owned_equipment');
        Schema::dropIfExists('dnd__restaurant_recipe_equipment');
        Schema::dropIfExists('dnd__restaurant_equipment');
        Schema::dropIfExists('dnd__restaurant_recipe_ingredient');
        Schema::dropIfExists('dnd__restaurant_ingredient');
        Schema::dropIfExists('dnd__restaurant_recipe');
        Schema::dropIfExists('dnd__restaurant_restaurant_user');
        Schema::dropIfExists('dnd__restaurant_restaurant');
    }
};
