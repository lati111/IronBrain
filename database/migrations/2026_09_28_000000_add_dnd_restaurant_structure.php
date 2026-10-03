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

            $table->date('date');
            $table->time('opening_time');
            $table->time('closing_time');
            $table->time('current_time')->nullable();

            $table->integer('bits')->default(0);
            $table->integer('copper')->default(0);
            $table->integer('silver')->default(0);
            $table->integer('gold')->default(0);
            // What the orders placed during the current service are worth, reset when a service starts
            $table->decimal('shift_total', 10, 2)->default(0);

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

            $table->integer('portions')->default(1);
            $table->integer('difficulty')->default(0);

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
            $table->decimal('size', 8, 2)->default(1);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_recipe_ingredient', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('recipe_uuid');
            $table->foreignUuid('ingredient_uuid');
            $table->decimal('amount', 10, 2)->default(1);
            $table->timestamps();

            // Explicit index names, as the generated ones exceed MySQL's 64 character limit
            $table->unique(['recipe_uuid', 'ingredient_uuid'], 'dnd__restaurant_recipe_ingredient_unique');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('ingredient_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_recipe_byproduct', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('recipe_uuid');
            $table->foreignUuid('ingredient_uuid');
            $table->decimal('amount', 10, 2)->default(1);
            $table->timestamps();

            $table->unique(['recipe_uuid', 'ingredient_uuid'], 'dnd__restaurant_recipe_byproduct_unique');
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
            // Holds the internal code of an EquipmentCategory
            $table->string('category', 32);
            $table->integer('cleaning_time')->default(0);
            $table->decimal('multiplier', 8, 2)->default(1.0);
            $table->integer('size')->default(1);
            // How many pieces are dirty or in use during service. The rest of the stored and owned pieces are clean
            $table->integer('amount_dirty')->default(0);
            $table->integer('amount_in_use')->default(0);
            $table->boolean('requires_storage')->default(true);

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
            // When during making the recipe the equipment is used, and whether it holds the meal until it is gone
            $table->string('stage', 16)->default('prep');
            $table->boolean('container')->default(false);
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
            $table->integer('order')->default(0);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_storage_ingredient', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('storage_uuid');
            $table->foreignUuid('ingredient_uuid');
            $table->decimal('amount', 10, 2)->default(1);
            $table->timestamps();

            $table->unique(['storage_uuid', 'ingredient_uuid'], 'dnd__restaurant_storage_ingredient_unique');
            $table->foreign('storage_uuid')->references('uuid')->on('dnd__restaurant_storage')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('ingredient_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_storage_equipment', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('storage_uuid');
            $table->foreignUuid('equipment_uuid');
            $table->integer('amount')->default(1);
            $table->timestamps();

            $table->unique(['storage_uuid', 'equipment_uuid'], 'dnd__restaurant_storage_equipment_unique');
            $table->foreign('storage_uuid')->references('uuid')->on('dnd__restaurant_storage')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('equipment_uuid')->references('uuid')->on('dnd__restaurant_equipment')->onDelete('cascade')->onUpdate('no action');
        });

        //| Floor
        Schema::create('dnd__restaurant_room', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');

            $table->string('name', 64);
            $table->text('background_image')->nullable();
            $table->integer('order')->default(0);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_table', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            // Null while the table has not been placed in a room
            $table->foreignUuid('room_uuid')->nullable();

            $table->string('name', 64);
            $table->integer('capacity')->default(1);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('room_uuid')->references('uuid')->on('dnd__restaurant_room')->nullOnDelete()->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_customer', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            // Both null while the customer is not seated. Seats are numbered from 1 up to the table's capacity
            $table->foreignUuid('table_uuid')->nullable();
            $table->integer('seat')->nullable();

            $table->string('name', 64);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['table_uuid', 'seat'], 'dnd__restaurant_customer_seat_unique');
            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('table_uuid')->references('uuid')->on('dnd__restaurant_table')->nullOnDelete()->onUpdate('no action');
        });

        //| Kitchen
        Schema::create('dnd__restaurant_meal', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->foreignUuid('recipe_uuid');

            // planned, prepped, cooking or serving. Whether a meal is done or spoiled follows from the timers
            $table->string('status', 16);
            // How many portions of a cooked meal are left to be plated
            $table->integer('portions_left')->default(1);

            // The cooker the meal is cooking in, and which piece of that equipment it occupies
            $table->foreignUuid('cooker_uuid')->nullable();
            $table->integer('cooker_slot')->nullable();

            // In the restaurant's in-game time
            $table->dateTime('cooking_ends_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            // Which ingredients were taken from which storage space, so a cancelled plan can be refunded
            $table->json('consumed')->nullable();
            // The recipe's equipment when the meal was planned, so later changes to the recipe don't affect it
            $table->json('equipment')->nullable();
            // Whether the equipment used for cooking has been released after the cooking finished
            $table->boolean('cooking_settled')->default(false);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('cooker_uuid')->references('uuid')->on('dnd__restaurant_equipment')->onDelete('cascade')->onUpdate('no action');
        });

        //| Service
        // A log of each service, opened when it starts and completed when it ends
        Schema::create('dnd__restaurant_service_history', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');

            // In the restaurant's in-game time. Ended at is null while the service is ongoing
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();

            // What the orders placed during the service were worth
            $table->decimal('profit', 10, 2)->default(0);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_order', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->foreignUuid('table_uuid')->nullable();
            // The service the order was placed during
            $table->foreignUuid('service_uuid')->nullable();

            // open, fulfilled once every entry is served, or finished once the meal is consumed
            $table->string('status', 16);
            $table->decimal('total_price', 10, 2)->default(0);

            // In the restaurant's in-game time
            $table->dateTime('ordered_at');
            $table->dateTime('fulfilled_at')->nullable();

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('table_uuid')->references('uuid')->on('dnd__restaurant_table')->nullOnDelete()->onUpdate('no action');
            $table->foreign('service_uuid')->references('uuid')->on('dnd__restaurant_service_history')->nullOnDelete()->onUpdate('no action');
        });

        // Each portion of a recipe ordered is an entry of its own
        Schema::create('dnd__restaurant_order_item', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('order_uuid');
            $table->foreignUuid('recipe_uuid')->nullable();
            // Null when ordered for the table as a whole
            $table->foreignUuid('customer_uuid')->nullable();

            // Kept, so the order still reads correctly when the recipe changes or is deleted
            $table->string('name', 64);
            $table->decimal('price', 10, 2)->default(0);
            $table->dateTime('served_at')->nullable();

            $table->timestamps();

            $table->foreign('order_uuid')->references('uuid')->on('dnd__restaurant_order')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->nullOnDelete()->onUpdate('no action');
            $table->foreign('customer_uuid')->references('uuid')->on('dnd__restaurant_customer')->nullOnDelete()->onUpdate('no action');
        });

        // A plated portion of a cooked meal, holding its serving equipment until it is eaten
        Schema::create('dnd__restaurant_served_meal', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->foreignUuid('recipe_uuid');
            // Set once it is brought to a table that ordered it
            $table->foreignUuid('order_item_uuid')->nullable();

            // The serving equipment in use, as equipment_uuid and amount
            $table->json('equipment')->nullable();

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('order_item_uuid')->references('uuid')->on('dnd__restaurant_order_item')->nullOnDelete()->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dnd__restaurant_served_meal');
        Schema::dropIfExists('dnd__restaurant_order_item');
        Schema::dropIfExists('dnd__restaurant_order');
        Schema::dropIfExists('dnd__restaurant_service_history');
        Schema::dropIfExists('dnd__restaurant_meal');
        Schema::dropIfExists('dnd__restaurant_customer');
        Schema::dropIfExists('dnd__restaurant_table');
        Schema::dropIfExists('dnd__restaurant_room');
        Schema::dropIfExists('dnd__restaurant_storage_equipment');
        Schema::dropIfExists('dnd__restaurant_storage_ingredient');
        Schema::dropIfExists('dnd__restaurant_storage');
        Schema::dropIfExists('dnd__restaurant_owned_equipment');
        Schema::dropIfExists('dnd__restaurant_recipe_equipment');
        Schema::dropIfExists('dnd__restaurant_equipment');
        Schema::dropIfExists('dnd__restaurant_recipe_byproduct');
        Schema::dropIfExists('dnd__restaurant_recipe_ingredient');
        Schema::dropIfExists('dnd__restaurant_ingredient');
        Schema::dropIfExists('dnd__restaurant_recipe');
        Schema::dropIfExists('dnd__restaurant_restaurant_user');
        Schema::dropIfExists('dnd__restaurant_restaurant');
    }
};
