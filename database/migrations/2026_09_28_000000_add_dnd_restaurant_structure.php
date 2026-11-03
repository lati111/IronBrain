<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The restaurant module's tables: restaurants and who has access, recipes and their variants,
     * ingredients, equipment and storage, the floor, staff and customers, the kitchen and its work queue, boosts, and
     * the orders, services and finances of a service
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
            // Counts the changes made to the restaurant, so refreshing pages can tell when nothing changed
            $table->unsignedBigInteger('state_version')->default(0);

            $table->timestamps();

            $table->foreign('owner_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_restaurant_user', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->foreignUuid('user_uuid');
            // Managers have full control as the owner has, players only control the employees assigned to them
            $table->string('role', 16)->default('player');
            $table->timestamps();

            $table->unique(['restaurant_uuid', 'user_uuid']);
            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('user_uuid')->references('uuid')->on('auth__user')->onDelete('cascade')->onUpdate('no action');
        });

        //| Recipe
        Schema::create('dnd__restaurant_recipe', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            // The recipe this one is a variant of
            $table->foreignUuid('parent_uuid')->nullable();

            $table->string('name', 64);
            $table->string('type', 64)->default('meal');
            $table->string('category', 64)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);

            $table->integer('portions')->default(1);
            $table->integer('difficulty')->default(0);

            $table->integer('prep_time')->default(0);
            $table->integer('cooking_time')->default(0);
            $table->integer('fresh_for')->default(0);
            $table->integer('good_for')->default(0);
            $table->integer('expires_in')->default(0);
            // What a variant adds to or removes from its base recipe, while its own columns and components hold the outcome
            $table->json('variant_changes')->nullable();

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        // Added separately, as the self-reference requires the table to exist first. Variants keep what they last were
        // as recipes of their own when their base recipe is removed directly
        Schema::table('dnd__restaurant_recipe', function (Blueprint $table) {
            $table->foreign('parent_uuid')->references('uuid')->on('dnd__restaurant_recipe')->nullOnDelete()->onUpdate('no action');
        });

        // Recipes can take any ingredient of a category, which is chosen when the meal is planned
        Schema::create('dnd__restaurant_recipe_category', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('recipe_uuid');
            $table->string('category', 64);
            $table->decimal('amount', 10, 2)->default(1);
            $table->timestamps();

            $table->unique(['recipe_uuid', 'category'], 'dnd__restaurant_recipe_category_unique');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
        });

        // Recipes can require other meals, either of a specific recipe or of any recipe of a category
        Schema::create('dnd__restaurant_recipe_meal', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('recipe_uuid');
            $table->foreignUuid('required_recipe_uuid')->nullable();
            $table->string('category', 64)->nullable();
            $table->unsignedInteger('portions')->default(1);
            $table->timestamps();

            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('required_recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
        });

        //| Ingredient
        Schema::create('dnd__restaurant_ingredient', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            // The ingredient this one is a variant of, to use instead of it
            $table->foreignUuid('parent_uuid')->nullable();

            $table->string('name', 64);
            $table->string('category', 64)->nullable();
            $table->text('icon')->nullable();
            $table->integer('expires_in')->default(0);
            $table->decimal('size', 8, 2)->default(1);
            // Added on top of how well meals made with it turn out
            $table->integer('quality')->default(0);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        // Added separately, as the self-reference requires the table to exist first
        Schema::table('dnd__restaurant_ingredient', function (Blueprint $table) {
            $table->foreign('parent_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->nullOnDelete()->onUpdate('no action');
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

        // Conversions turn an ingredient into another, which during service is a job in the prep area
        Schema::create('dnd__restaurant_conversion', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            // Each piece of the input ingredient becomes an amount of the output ingredient
            $table->foreignUuid('input_uuid');
            $table->foreignUuid('output_uuid');
            $table->decimal('output_amount', 10, 2)->default(1);
            // In minutes at normal speed, per piece of the input ingredient converted during service
            $table->decimal('minutes', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('input_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('output_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
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
            $table->decimal('cleaning_time', 8, 2)->default(0);
            $table->decimal('multiplier', 8, 2)->default(1.0);
            // What meals made with the equipment gain in quality: a flat amount, and a multiplier that adds up with those
            // of the other equipment used, so 0.1 and 0.2 together make 30% more
            $table->integer('quality_bonus')->default(0);
            $table->decimal('quality_multiplier', 8, 2)->default(0);
            $table->decimal('size', 8, 2)->default(1);
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
            // How many days are left before the stack expires, so an ingredient is stored in stacks of different freshness
            $table->decimal('days_left', 8, 2)->default(0);
            $table->timestamps();

            $table->index('storage_uuid', 'dnd__restaurant_storage_ingredient_storage_uuid_index');
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

        // Ingredients brought in during service, waiting in a pile to be sorted into the storage spaces
        Schema::create('dnd__restaurant_pile_ingredient', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->foreignUuid('ingredient_uuid');
            $table->decimal('amount', 10, 2)->default(1);
            // How many days are left before the stack expires, so an ingredient is stored in stacks of different freshness
            $table->decimal('days_left', 8, 2)->default(0);
            $table->timestamps();

            $table->index('restaurant_uuid', 'dnd__restaurant_pile_ingredient_restaurant_uuid_index');
            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('ingredient_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
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
            // The employee waiting the table, who takes its orders
            $table->foreignUuid('employee_uuid')->nullable();

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
            // When the customer arrived at the entrance, in the restaurant's time, while they wait there to be seated
            $table->dateTime('entered_at')->nullable();

            $table->string('name', 64);
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['table_uuid', 'seat'], 'dnd__restaurant_customer_seat_unique');
            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('table_uuid')->references('uuid')->on('dnd__restaurant_table')->nullOnDelete()->onUpdate('no action');
        });

        //| Staff
        Schema::create('dnd__restaurant_employee', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            // The player controlling the employee during service, if any
            $table->foreignUuid('player_uuid')->nullable();

            $table->string('name', 64);
            $table->text('icon')->nullable();
            // How much faster than normal the employee works, so 2 works twice as fast
            $table->decimal('cleaning_speed', 8, 2)->default(1);
            $table->decimal('prep_speed', 8, 2)->default(1);
            // How many plates the employee carries to a table in one trip, as a waiter
            $table->unsignedInteger('capacity')->default(2);
            // The quality a cook adds to the meals in the cookers over their whole cooking time, while they look after them
            $table->integer('cooking_quality')->default(0);

            // The work areas the employee works in during service, as a list of prep, cooking or cleaning. Prep and cooking
            // can be combined, which halves the speed of prepping. Null while idle or waiting
            $table->json('stations')->nullable();

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('player_uuid')->references('uuid')->on('auth__user')->nullOnDelete()->onUpdate('no action');
        });

        // Added separately, as tables come before employees
        Schema::table('dnd__restaurant_table', function (Blueprint $table) {
            $table->foreign('employee_uuid')->references('uuid')->on('dnd__restaurant_employee')->nullOnDelete()->onUpdate('no action');
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

            // How well the meal turned out, what boosts and ingredients added to it, and minutes boosts add to its fresh for time
            $table->integer('quality')->nullable();
            $table->integer('quality_bonus')->default(0);
            $table->integer('ingredient_quality')->default(0);
            $table->integer('fresh_for_bonus')->default(0);
            // What the equipment used adds to the quality: a flat amount, and a multiplier on how well it turned out
            $table->integer('equipment_bonus')->default(0);
            $table->decimal('equipment_multiplier', 8, 2)->default(0);

            // The cooker the meal is cooking in, and which piece of that equipment it occupies
            $table->foreignUuid('cooker_uuid')->nullable();
            $table->integer('cooker_slot')->nullable();

            // In the restaurant's in-game time
            $table->dateTime('cooking_ends_at')->nullable();
            // Minutes of cooking done at normal speed
            $table->decimal('cooking_progress', 10, 2)->default(0);
            // How the cooking went, in minutes of the restaurant's time spent cooking, of which were without a cook (a cook
            // busy prepping counting for part), and the skill of the cooks over it. The cooking quality follows from these
            $table->decimal('cooking_minutes', 10, 2)->default(0);
            $table->decimal('cooking_absent', 10, 2)->default(0);
            $table->decimal('cooking_skill', 10, 2)->default(0);
            $table->dateTime('expires_at')->nullable();

            // Which ingredients were taken from which storage space, so a cancelled plan can be refunded
            $table->json('consumed')->nullable();
            // The recipe's equipment when the meal was planned, so later changes to the recipe don't affect it
            $table->json('equipment')->nullable();
            // The meals of other recipes that have been put in, and the equipment carried over from them
            $table->json('supplied')->nullable();
            $table->json('carried_equipment')->nullable();
            // Whether the equipment used for cooking has been released after the cooking finished
            $table->boolean('cooking_settled')->default(false);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('cooker_uuid')->references('uuid')->on('dnd__restaurant_equipment')->onDelete('cascade')->onUpdate('no action');
        });

        // Boosts are configured per restaurant and activated during service on any of their targets
        Schema::create('dnd__restaurant_boost', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->string('name', 64);
            // What it can be activated on: a cooker, other equipment, an area, or a meal by how far along it is
            $table->json('targets')->nullable();
            // Added to the speed of what it is on while it lasts, as a multiplier is
            $table->decimal('speed_bonus', 8, 2)->default(0);
            // Minutes of prep or cooking done straight away when activated
            $table->decimal('minutes_off', 8, 2)->default(0);
            // Added to the quality of a meal it is activated on
            $table->integer('quality_bonus')->default(0);
            // Minutes added to how long a meal it is activated on stays fresh once served
            $table->integer('fresh_for_bonus')->default(0);
            // How many minutes of the restaurant's time the speed bonus lasts
            $table->unsignedInteger('duration')->default(0);
            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
        });

        Schema::create('dnd__restaurant_active_boost', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            // The boost it was activated from, if it still exists. Its name and bonus are kept as they were then
            $table->foreignUuid('boost_uuid')->nullable();
            $table->string('name', 64);
            $table->decimal('speed_bonus', 8, 2)->default(0);
            // What it is on: a piece of equipment, of which a cooker can have a single piece, a meal, or an area
            $table->foreignUuid('equipment_uuid')->nullable();
            $table->unsignedInteger('cooker_slot')->nullable();
            $table->foreignUuid('meal_uuid')->nullable();
            $table->string('area', 16)->nullable();
            // In the restaurant's in-game time
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['restaurant_uuid', 'expires_at']);
            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('boost_uuid')->references('uuid')->on('dnd__restaurant_boost')->nullOnDelete()->onUpdate('no action');
            $table->foreign('equipment_uuid')->references('uuid')->on('dnd__restaurant_equipment')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('meal_uuid')->references('uuid')->on('dnd__restaurant_meal')->onDelete('cascade')->onUpdate('no action');
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

        // Money going in and out of the restaurant: the profit of each service, and what is added by hand
        Schema::create('dnd__restaurant_transaction', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            // What the transaction was: the profit of a service, or one added by hand
            $table->string('type', 32);
            $table->string('title', 255);
            $table->text('description')->nullable();
            // In bits, negative when money went out
            $table->decimal('amount', 12, 2)->default(0);
            // The service the transaction is the profit of
            $table->foreignUuid('service_uuid')->nullable();
            // In the restaurant's in-game time
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['restaurant_uuid', 'occurred_at']);
            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('service_uuid')->references('uuid')->on('dnd__restaurant_service_history')->nullOnDelete()->onUpdate('no action');
        });

        // A recipe sold at another price during one service only
        Schema::create('dnd__restaurant_sale', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('service_uuid');
            $table->foreignUuid('recipe_uuid');
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['service_uuid', 'recipe_uuid'], 'dnd__restaurant_sale_unique');
            $table->foreign('service_uuid')->references('uuid')->on('dnd__restaurant_service_history')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
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
            // In bits, added to and taken off the price of the items when the order is paid
            $table->decimal('tip', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);

            // In the restaurant's in-game time
            $table->dateTime('ordered_at');
            $table->dateTime('fulfilled_at')->nullable();
            // When the order's money went into the shift total. Null until it is paid
            $table->dateTime('paid_at')->nullable();

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
            // When the portion was eaten, which returns its serving equipment dirty
            $table->dateTime('consumed_at')->nullable();
            // How satisfied the customer was with the portion once it was served, from 1 to 5
            $table->unsignedTinyInteger('satisfaction')->nullable();

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
            // How well the meal it was plated from turned out, and when it spoils, in the restaurant's in-game time
            $table->integer('quality')->nullable();
            $table->dateTime('expires_at')->nullable();

            // The serving equipment in use, as equipment_uuid and amount
            $table->json('equipment')->nullable();

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('order_item_uuid')->references('uuid')->on('dnd__restaurant_order_item')->nullOnDelete()->onUpdate('no action');
        });

        // The jobs of the work areas and of the waiters. Prep, cleaning and conversions are done in order by the
        // employees working in their area, while the jobs of waiters are done by whoever waits the table
        Schema::create('dnd__restaurant_work', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');

            // prep, cleaning or waiting
            $table->string('station', 16);
            // The meal being prepped, or the equipment being cleaned along with how many pieces
            $table->foreignUuid('meal_uuid')->nullable();
            $table->foreignUuid('equipment_uuid')->nullable();
            // The table a waiter's job is for, the order they are taking, or the plated portion they are bringing
            $table->foreignUuid('table_uuid')->nullable();
            $table->foreignUuid('order_uuid')->nullable();
            $table->foreignUuid('served_meal_uuid')->nullable();
            // The customer being seated, and the seat at the table they are taken to
            $table->foreignUuid('customer_uuid')->nullable();
            $table->unsignedInteger('seat')->nullable();
            // The job of the trip this plate is carried along on, which does the trip's work for all of them
            $table->foreignUuid('carrier_uuid')->nullable();
            // A conversion being done in the prep area: how much it takes and makes, and what it took from storage
            $table->foreignUuid('input_uuid')->nullable();
            $table->decimal('input_amount', 10, 2)->nullable();
            $table->foreignUuid('output_uuid')->nullable();
            $table->decimal('output_amount', 10, 2)->nullable();
            $table->json('consumed')->nullable();
            $table->integer('amount')->default(1);

            // In minutes at normal speed: how much the job takes in total, and how much of it has been done
            $table->decimal('work', 10, 2)->default(0);
            $table->decimal('progress', 10, 2)->default(0);
            // The place in the queue of its work area
            $table->integer('order')->default(0);

            $table->timestamps();

            $table->foreign('restaurant_uuid')->references('uuid')->on('dnd__restaurant_restaurant')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('meal_uuid')->references('uuid')->on('dnd__restaurant_meal')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('equipment_uuid')->references('uuid')->on('dnd__restaurant_equipment')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('table_uuid')->references('uuid')->on('dnd__restaurant_table')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('order_uuid')->references('uuid')->on('dnd__restaurant_order')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('served_meal_uuid')->references('uuid')->on('dnd__restaurant_served_meal')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('customer_uuid')->references('uuid')->on('dnd__restaurant_customer')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('input_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('output_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
        });

        // Added separately, as the self-reference requires the table to exist first
        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            $table->foreign('carrier_uuid')->references('uuid')->on('dnd__restaurant_work')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dnd__restaurant_work');
        Schema::dropIfExists('dnd__restaurant_served_meal');
        Schema::dropIfExists('dnd__restaurant_order_item');
        Schema::dropIfExists('dnd__restaurant_order');
        Schema::dropIfExists('dnd__restaurant_sale');
        Schema::dropIfExists('dnd__restaurant_transaction');
        Schema::dropIfExists('dnd__restaurant_service_history');
        Schema::dropIfExists('dnd__restaurant_active_boost');
        Schema::dropIfExists('dnd__restaurant_boost');
        Schema::dropIfExists('dnd__restaurant_meal');
        Schema::dropIfExists('dnd__restaurant_customer');
        Schema::dropIfExists('dnd__restaurant_table');
        Schema::dropIfExists('dnd__restaurant_room');
        Schema::dropIfExists('dnd__restaurant_employee');
        Schema::dropIfExists('dnd__restaurant_pile_ingredient');
        Schema::dropIfExists('dnd__restaurant_storage_equipment');
        Schema::dropIfExists('dnd__restaurant_storage_ingredient');
        Schema::dropIfExists('dnd__restaurant_storage');
        Schema::dropIfExists('dnd__restaurant_owned_equipment');
        Schema::dropIfExists('dnd__restaurant_recipe_equipment');
        Schema::dropIfExists('dnd__restaurant_equipment');
        Schema::dropIfExists('dnd__restaurant_conversion');
        Schema::dropIfExists('dnd__restaurant_recipe_byproduct');
        Schema::dropIfExists('dnd__restaurant_recipe_ingredient');
        Schema::dropIfExists('dnd__restaurant_ingredient');
        Schema::dropIfExists('dnd__restaurant_recipe_meal');
        Schema::dropIfExists('dnd__restaurant_recipe_category');
        Schema::dropIfExists('dnd__restaurant_recipe');
        Schema::dropIfExists('dnd__restaurant_restaurant_user');
        Schema::dropIfExists('dnd__restaurant_restaurant');
    }
};
