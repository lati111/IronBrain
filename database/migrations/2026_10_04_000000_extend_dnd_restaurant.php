<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    }

    /**
     * Reverse the migrations, in the opposite order
     */
    public function down(): void
    {
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
};
