<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Boosts are configured per restaurant and activated during service, speeding up equipment,
     * a meal or a whole area for a while, cutting time off straight away, or improving a meal. Cooking keeps the
     * progress made rather than only when it ends, so time cooked at a boosted rate stays cooked once a boost runs out.
     * Meals cooking so far get the progress their end time comes down to, so they still finish when they would have
     */
    public function up(): void
    {
        Schema::create('dnd__restaurant_boost', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('restaurant_uuid');
            $table->string('name', 64);
            // What it is activated on: a piece of equipment, a meal, or a whole area
            $table->string('target', 16);
            // Added to the speed of what it is on while it lasts, as a multiplier is
            $table->decimal('speed_bonus', 8, 2)->default(0);
            // Minutes of prep or cooking done straight away when activated
            $table->decimal('minutes_off', 8, 2)->default(0);
            // Added to the quality of a meal it is activated on
            $table->integer('quality_bonus')->default(0);
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
            // What it is on: a piece of equipment, a meal, or an area
            $table->foreignUuid('equipment_uuid')->nullable();
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

        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            // Minutes of cooking done at normal speed
            $table->decimal('cooking_progress', 10, 2)->default(0)->after('cooking_ends_at');
            // Added to the quality the meal turned out at, by boosts
            $table->integer('quality_bonus')->default(0)->after('quality');
        });

        // Meals in a cooker have cooked for as long as their end time leaves of their recipe's cooking time
        $cooking = DB::table('dnd__restaurant_meal')
            ->join('dnd__restaurant_recipe', 'dnd__restaurant_recipe.uuid', '=', 'dnd__restaurant_meal.recipe_uuid')
            ->join('dnd__restaurant_restaurant', 'dnd__restaurant_restaurant.uuid', '=', 'dnd__restaurant_meal.restaurant_uuid')
            ->where('dnd__restaurant_meal.status', 'cooking')
            ->whereNotNull('dnd__restaurant_meal.cooking_ends_at')
            ->select('dnd__restaurant_meal.uuid', 'dnd__restaurant_meal.cooking_ends_at', 'dnd__restaurant_recipe.cooking_time',
                'dnd__restaurant_restaurant.date', 'dnd__restaurant_restaurant.current_time', 'dnd__restaurant_restaurant.opening_time')
            ->get();

        foreach ($cooking as $meal) {
            $now = Carbon::parse(substr($meal->date, 0, 10) . ' ' . ($meal->current_time ?? $meal->opening_time));
            $remaining = max(0, (Carbon::parse($meal->cooking_ends_at)->getTimestamp() - $now->getTimestamp()) / 60);
            $progress = max(0, min($meal->cooking_time, $meal->cooking_time - $remaining));

            DB::table('dnd__restaurant_meal')->where('uuid', $meal->uuid)->update(['cooking_progress' => round($progress, 2)]);
        }
    }

    /**
     * Reverse the migrations. Cooking goes back to only knowing when it ends, which the meals still have
     */
    public function down(): void
    {
        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->dropColumn(['cooking_progress', 'quality_bonus']);
        });

        Schema::dropIfExists('dnd__restaurant_active_boost');
        Schema::dropIfExists('dnd__restaurant_boost');
    }
};
