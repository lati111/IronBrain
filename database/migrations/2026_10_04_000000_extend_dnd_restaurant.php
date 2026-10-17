<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Extends the restaurant structure for service and inventory management. Everything that already exists keeps its
 * data: new columns start empty or at a value matching how things worked before, and existing values are converted
 * as they are
 */
return new class extends Migration
{
    /** @var array<string, array{string, string}> The tables of stored ingredients, with the unique index of one stack
     *                                            per ingredient, and the column whose foreign key relied on it */
    private const array STACK_TABLES = [
        'dnd__restaurant_storage_ingredient' => ['dnd__restaurant_storage_ingredient_unique', 'storage_uuid'],
        'dnd__restaurant_pile_ingredient'    => ['dnd__restaurant_pile_ingredient_unique', 'restaurant_uuid'],
    ];

    /**
     * Run the migrations
     */
    public function up(): void
    {
        $this->upEquipmentDecimals();
        $this->upOrderPayment();
        $this->upTableWaiter();
        $this->upMealQuality();
        $this->upWaitingWork();
        $this->upIngredientExpiry();
        $this->upIngredientCategories();
        $this->upRecipeMeals();
        $this->upConversions();
        $this->upTransactions();
        $this->upPlayers();
        $this->upSatisfaction();
        $this->upStateVersion();
        $this->upBoosts();
        $this->upIngredientQuality();
        $this->upRecipeVariants();
    }

    /**
     * Reverse the migrations, in the opposite order
     */
    public function down(): void
    {
        $this->downRecipeVariants();
        $this->downIngredientQuality();
        $this->downBoosts();
        $this->downStateVersion();
        $this->downSatisfaction();
        $this->downPlayers();
        $this->downTransactions();
        $this->downConversions();
        $this->downRecipeMeals();
        $this->downIngredientCategories();
        $this->downIngredientExpiry();
        $this->downWaitingWork();
        $this->downMealQuality();
        $this->downTableWaiter();
        $this->downOrderPayment();
        $this->downEquipmentDecimals();
    }

    //| Equipment decimals

    /**
     * Equipment can take up part of a space and be cleaned in part of a minute. Whole numbers convert to decimals as
     * they are, so existing equipment keeps its size and cleaning time
     */
    private function upEquipmentDecimals(): void
    {
        Schema::table('dnd__restaurant_equipment', function (Blueprint $table) {
            $table->decimal('cleaning_time', 8, 2)->default(0)->change();
            $table->decimal('size', 8, 2)->default(1)->change();
        });
    }

    /**
     * Fractions are rounded to whole numbers, never below the smallest allowed value
     */
    private function downEquipmentDecimals(): void
    {
        DB::table('dnd__restaurant_equipment')->update([
            'cleaning_time' => DB::raw('ROUND(cleaning_time)'),
            'size' => DB::raw('GREATEST(ROUND(size), 1)'),
        ]);

        Schema::table('dnd__restaurant_equipment', function (Blueprint $table) {
            $table->integer('cleaning_time')->default(0)->change();
            $table->integer('size')->default(1)->change();
        });
    }

    //| Order payment

    /**
     * Orders get a tip and a discount, and are paid into the shift total once they are finished rather than when they
     * are placed. Items are eaten one by one
     */
    private function upOrderPayment(): void
    {
        Schema::table('dnd__restaurant_order', function (Blueprint $table) {
            // In bits, added to and taken off the price of the items when the order is paid
            $table->decimal('tip', 10, 2)->default(0)->after('total_price');
            $table->decimal('discount', 10, 2)->default(0)->after('tip');
            // When the order's money went into the shift total, in the restaurant's time. Null until it is paid
            $table->dateTime('paid_at')->nullable()->after('fulfilled_at');
        });

        Schema::table('dnd__restaurant_order_item', function (Blueprint $table) {
            // When the portion was eaten, which returns its serving equipment dirty
            $table->dateTime('consumed_at')->nullable()->after('served_at');
        });

        // Orders placed so far were paid into the shift total as they were placed, so they mustn't be paid again
        DB::table('dnd__restaurant_order')->update(['paid_at' => DB::raw('ordered_at')]);

        // Finished orders were eaten in full
        DB::table('dnd__restaurant_order_item')
            ->whereNotNull('served_at')
            ->whereIn('order_uuid', DB::table('dnd__restaurant_order')->where('status', 'finished')->select('uuid'))
            ->update(['consumed_at' => DB::raw('served_at')]);
    }

    private function downOrderPayment(): void
    {
        Schema::table('dnd__restaurant_order_item', function (Blueprint $table) {
            $table->dropColumn('consumed_at');
        });

        Schema::table('dnd__restaurant_order', function (Blueprint $table) {
            $table->dropColumn(['tip', 'discount', 'paid_at']);
        });
    }

    //| Table waiter

    /**
     * Tables get an employee waiting them, who takes their orders. Existing tables start without
     */
    private function upTableWaiter(): void
    {
        Schema::table('dnd__restaurant_table', function (Blueprint $table) {
            $table->foreignUuid('employee_uuid')->nullable()->after('room_uuid');
            $table->foreign('employee_uuid')->references('uuid')->on('dnd__restaurant_employee')->nullOnDelete()->onUpdate('no action');
        });
    }

    private function downTableWaiter(): void
    {
        Schema::table('dnd__restaurant_table', function (Blueprint $table) {
            $table->dropForeign(['employee_uuid']);
            $table->dropColumn('employee_uuid');
        });
    }

    //| Meal quality

    /**
     * Finished meals get the quality they turned out at, which their plated portions carry along with when they
     * spoil. Existing meals and portions start without, so nothing already there changes
     */
    private function upMealQuality(): void
    {
        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->integer('quality')->nullable()->after('portions_left');
        });

        Schema::table('dnd__restaurant_served_meal', function (Blueprint $table) {
            $table->integer('quality')->nullable()->after('order_item_uuid');
            // In the restaurant's in-game time
            $table->dateTime('expires_at')->nullable()->after('quality');
        });
    }

    private function downMealQuality(): void
    {
        Schema::table('dnd__restaurant_served_meal', function (Blueprint $table) {
            $table->dropColumn(['quality', 'expires_at']);
        });

        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->dropColumn('quality');
        });
    }

    //| Waiting work

    /**
     * Waiters get jobs of their own in the work queue: taking a table's order, and bringing a plated portion to it.
     * Existing prep and cleaning jobs aren't tied to a table, so they stay as they are
     */
    private function upWaitingWork(): void
    {
        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            // The table the job is for, done by whoever waits it
            $table->foreignUuid('table_uuid')->nullable()->after('equipment_uuid');
            // The order being taken, or the plated portion being brought
            $table->foreignUuid('order_uuid')->nullable()->after('table_uuid');
            $table->foreignUuid('served_meal_uuid')->nullable()->after('order_uuid');

            $table->foreign('table_uuid')->references('uuid')->on('dnd__restaurant_table')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('order_uuid')->references('uuid')->on('dnd__restaurant_order')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('served_meal_uuid')->references('uuid')->on('dnd__restaurant_served_meal')->onDelete('cascade')->onUpdate('no action');
        });
    }

    /**
     * Waiters' jobs can't exist without these, so they are removed first
     */
    private function downWaitingWork(): void
    {
        DB::table('dnd__restaurant_work')->where('station', 'waiting')->delete();

        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            $table->dropForeign(['table_uuid']);
            $table->dropForeign(['order_uuid']);
            $table->dropForeign(['served_meal_uuid']);
            $table->dropColumn(['table_uuid', 'order_uuid', 'served_meal_uuid']);
        });
    }

    //| Ingredient expiry

    /**
     * Stored ingredients keep how many days they have left before they expire, so the same ingredient can be stored
     * in several stacks of different freshness. Stacks stored so far start fresh, with the days their ingredient
     * lasts, and keep their amounts
     */
    private function upIngredientExpiry(): void
    {
        foreach (self::STACK_TABLES as $tableName => [$unique, $column]) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $unique, $column) {
                $table->decimal('days_left', 8, 2)->default(0)->after('amount');
                // The foreign key used the unique index, so it gets an index of its own before that one goes
                $table->index($column, $tableName . '_' . $column . '_index');
                $table->dropUnique($unique);
            });

            DB::table($tableName)->update([
                'days_left' => DB::raw('(SELECT expires_in FROM dnd__restaurant_ingredient WHERE dnd__restaurant_ingredient.uuid = ' . $tableName . '.ingredient_uuid)'),
            ]);
        }
    }

    /**
     * Stacks of the same ingredient are merged back into one, keeping their total amount
     */
    private function downIngredientExpiry(): void
    {
        foreach (self::STACK_TABLES as $tableName => [$unique, $column]) {
            $groups = DB::table($tableName)
                ->select($column, 'ingredient_uuid', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as stacks'))
                ->groupBy($column, 'ingredient_uuid')
                ->having('stacks', '>', 1)
                ->get();

            foreach ($groups as $group) {
                $stacks = DB::table($tableName)->where($column, $group->{$column})->where('ingredient_uuid', $group->ingredient_uuid)
                    ->orderBy('created_at')->pluck('uuid');
                DB::table($tableName)->where('uuid', $stacks->first())->update(['amount' => $group->total]);
                DB::table($tableName)->whereIn('uuid', $stacks->slice(1)->all())->delete();
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $unique, $column) {
                $table->unique([$column, 'ingredient_uuid'], $unique);
                $table->dropIndex($tableName . '_' . $column . '_index');
                $table->dropColumn('days_left');
            });
        }
    }

    //| Ingredient categories

    /**
     * Ingredients can have a category, and recipes can take any ingredient of a category, which is chosen when the
     * meal is planned. Existing ingredients start without a category
     */
    private function upIngredientCategories(): void
    {
        Schema::table('dnd__restaurant_ingredient', function (Blueprint $table) {
            $table->string('category', 64)->nullable()->after('name');
        });

        Schema::create('dnd__restaurant_recipe_category', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('recipe_uuid');
            $table->string('category', 64);
            $table->decimal('amount', 10, 2)->default(1);
            $table->timestamps();

            $table->unique(['recipe_uuid', 'category'], 'dnd__restaurant_recipe_category_unique');
            $table->foreign('recipe_uuid')->references('uuid')->on('dnd__restaurant_recipe')->onDelete('cascade')->onUpdate('no action');
        });
    }

    private function downIngredientCategories(): void
    {
        Schema::dropIfExists('dnd__restaurant_recipe_category');

        Schema::table('dnd__restaurant_ingredient', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    //| Recipe meals

    /**
     * Recipes get a type and a category, and can require other meals, either of a specific recipe or of any recipe of
     * a category. Meals track which of those have been put in, and the equipment carried over from them. Existing
     * recipes become meals without a category
     */
    private function upRecipeMeals(): void
    {
        Schema::table('dnd__restaurant_recipe', function (Blueprint $table) {
            $table->string('type', 64)->default('meal')->after('name');
            $table->string('category', 64)->nullable()->after('type');
        });

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

        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->json('supplied')->nullable()->after('equipment');
            $table->json('carried_equipment')->nullable()->after('supplied');
        });
    }

    private function downRecipeMeals(): void
    {
        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->dropColumn(['supplied', 'carried_equipment']);
        });

        Schema::dropIfExists('dnd__restaurant_recipe_meal');

        Schema::table('dnd__restaurant_recipe', function (Blueprint $table) {
            $table->dropColumn(['type', 'category']);
        });
    }

    //| Conversions

    /**
     * Conversions turn an ingredient into another, which during service is a job in the prep area that keeps the
     * ingredients it took, so they can be put back when it is cancelled
     */
    private function upConversions(): void
    {
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

        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            // A conversion being done in the prep area: how much it takes and makes, and what it took from storage
            $table->foreignUuid('input_uuid')->nullable()->after('served_meal_uuid');
            $table->decimal('input_amount', 10, 2)->nullable()->after('input_uuid');
            $table->foreignUuid('output_uuid')->nullable()->after('input_amount');
            $table->decimal('output_amount', 10, 2)->nullable()->after('output_uuid');
            $table->json('consumed')->nullable()->after('output_amount');

            $table->foreign('input_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
            $table->foreign('output_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->onDelete('cascade')->onUpdate('no action');
        });
    }

    private function downConversions(): void
    {
        Schema::table('dnd__restaurant_work', function (Blueprint $table) {
            $table->dropConstrainedForeignId('input_uuid');
            $table->dropConstrainedForeignId('output_uuid');
            $table->dropColumn(['input_amount', 'output_amount', 'consumed']);
        });

        Schema::dropIfExists('dnd__restaurant_conversion');
    }

    //| Transactions

    /**
     * Money going in and out of a restaurant is logged as transactions, both the profit of each service and those
     * added by hand. Services that already ended get the transaction of their profit, so the log covers everything
     * paid into the restaurant so far
     */
    private function upTransactions(): void
    {
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

        $services = DB::table('dnd__restaurant_service_history')->whereNotNull('ended_at')->orderBy('ended_at')->get();
        foreach ($services as $service) {
            DB::table('dnd__restaurant_transaction')->insert([
                'uuid' => (string) Str::uuid7(),
                'restaurant_uuid' => $service->restaurant_uuid,
                'type' => 'service',
                'title' => 'Daily finances',
                'amount' => $service->profit,
                'service_uuid' => $service->uuid,
                'occurred_at' => $service->ended_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * The services themselves keep their profit
     */
    private function downTransactions(): void
    {
        Schema::dropIfExists('dnd__restaurant_transaction');
    }

    //| Players

    /**
     * Users given access to a restaurant are either managers, with full control as the owner has, or players, who only
     * control the employees assigned to them and move ingredients around. Access granted so far was full access, so it
     * stays that way. Employees can be assigned to a player
     */
    private function upPlayers(): void
    {
        Schema::table('dnd__restaurant_restaurant_user', function (Blueprint $table) {
            $table->string('role', 16)->default('player')->after('user_uuid');
        });

        DB::table('dnd__restaurant_restaurant_user')->update(['role' => 'manager']);

        Schema::table('dnd__restaurant_employee', function (Blueprint $table) {
            // The player controlling the employee during service, if any
            $table->foreignUuid('player_uuid')->nullable()->after('restaurant_uuid');
            $table->foreign('player_uuid')->references('uuid')->on('auth__user')->nullOnDelete()->onUpdate('no action');
        });
    }

    /**
     * Players lose their access, as without roles access is always full access
     */
    private function downPlayers(): void
    {
        Schema::table('dnd__restaurant_employee', function (Blueprint $table) {
            $table->dropForeign(['player_uuid']);
            $table->dropColumn('player_uuid');
        });

        DB::table('dnd__restaurant_restaurant_user')->where('role', 'player')->delete();

        Schema::table('dnd__restaurant_restaurant_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    //| Satisfaction

    /**
     * Each portion is rated by how satisfied its customer was once it is served. Portions served so far weren't rated,
     * so they stay without
     */
    private function upSatisfaction(): void
    {
        Schema::table('dnd__restaurant_order_item', function (Blueprint $table) {
            // From 1 to 5
            $table->unsignedTinyInteger('satisfaction')->nullable()->after('consumed_at');
        });
    }

    private function downSatisfaction(): void
    {
        Schema::table('dnd__restaurant_order_item', function (Blueprint $table) {
            $table->dropColumn('satisfaction');
        });
    }

    //| State version

    /**
     * Restaurants count the changes made to them, so pages that refresh can tell when nothing changed without fetching
     * everything again
     */
    private function upStateVersion(): void
    {
        Schema::table('dnd__restaurant_restaurant', function (Blueprint $table) {
            $table->unsignedBigInteger('state_version')->default(0)->after('shift_total');
        });
    }

    private function downStateVersion(): void
    {
        Schema::table('dnd__restaurant_restaurant', function (Blueprint $table) {
            $table->dropColumn('state_version');
        });
    }

    //| Boosts

    /**
     * Boosts are configured per restaurant and activated during service, speeding up equipment, a single cooker, a
     * meal or a whole area for a while, cutting time off straight away, or improving a meal. Cooking keeps the
     * progress made rather than only when it ends, so time cooked at a boosted rate stays cooked once a boost runs out.
     * Meals cooking so far get the progress their end time comes down to, so they still finish when they would have
     */
    private function upBoosts(): void
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
     * Cooking goes back to only knowing when it ends, which the meals still have
     */
    private function downBoosts(): void
    {
        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->dropColumn(['cooking_progress', 'quality_bonus']);
        });

        Schema::dropIfExists('dnd__restaurant_active_boost');
        Schema::dropIfExists('dnd__restaurant_boost');
    }

    //| Ingredient quality

    /**
     * Ingredients get a quality, which meals made with them add on top of how well they turned out, and can be
     * variants of another ingredient to use instead of it. Existing ingredients are of quality 0, so meals made so far
     * keep the quality they are served at
     */
    private function upIngredientQuality(): void
    {
        Schema::table('dnd__restaurant_ingredient', function (Blueprint $table) {
            $table->foreignUuid('parent_uuid')->nullable()->after('restaurant_uuid');
            $table->integer('quality')->default(0)->after('size');
            $table->foreign('parent_uuid')->references('uuid')->on('dnd__restaurant_ingredient')->nullOnDelete()->onUpdate('no action');
        });

        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->integer('ingredient_quality')->default(0)->after('quality_bonus');
        });
    }

    private function downIngredientQuality(): void
    {
        Schema::table('dnd__restaurant_meal', function (Blueprint $table) {
            $table->dropColumn('ingredient_quality');
        });

        Schema::table('dnd__restaurant_ingredient', function (Blueprint $table) {
            $table->dropForeign(['parent_uuid']);
            $table->dropColumn(['parent_uuid', 'quality']);
        });
    }

    //| Recipe variants

    /**
     * A recipe can be a variant of another, adding to or removing from what the base recipe takes and adding to its
     * stats. The changes are kept apart, while its own columns and components hold the outcome, so the rest of the
     * restaurant uses a variant as any other recipe. Existing recipes are no variant, so stay as they are. Should a
     * base recipe be removed directly, its variants keep what they last were as recipes of their own
     */
    private function upRecipeVariants(): void
    {
        Schema::table('dnd__restaurant_recipe', function (Blueprint $table) {
            $table->foreignUuid('parent_uuid')->nullable()->after('restaurant_uuid');
            $table->json('variant_changes')->nullable()->after('expires_in');
            $table->foreign('parent_uuid')->references('uuid')->on('dnd__restaurant_recipe')->nullOnDelete()->onUpdate('no action');
        });
    }

    private function downRecipeVariants(): void
    {
        Schema::table('dnd__restaurant_recipe', function (Blueprint $table) {
            $table->dropForeign(['parent_uuid']);
            $table->dropColumn(['parent_uuid', 'variant_changes']);
        });
    }
};
