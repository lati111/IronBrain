<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Boost;
use App\Models\DnD\Restaurant\Conversion;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\PileIngredient;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeCategory;
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\RecipeMeal;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageIngredient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KitchenService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly BoostService $boostService,
    ) {
    }

    /**
     * Builds the state of the restaurant's service: its clock, and its kitchen with the cookers, the other
     * equipment, the recipes that can be planned and the meals being made
     * @param Restaurant $restaurant The restaurant to build the state for
     * @return array The service state
     */
    public function getState(Restaurant $restaurant): array
    {
        // Meals that finished cooking since the clock last moved release their cooking equipment first
        $this->settleFinishedMeals($restaurant);

        $now = $restaurant->getNow();
        $equipment = Equipment::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get();
        $meals = Meal::where('restaurant_uuid', $restaurant->uuid)->with('Recipe.RecipeMeals.RequiredRecipe')->orderBy('created_at')->get();
        $sales = $restaurant->getSalePrices();
        $ingredients = $this->describeIngredients($restaurant);
        $clean = $equipment->mapWithKeys(fn (Equipment $piece) => [$piece->uuid => $this->getCleanAmount($piece)]);

        return [
            'clock' => [
                'date' => $now->format('Y-m-d'),
                'time' => $now->format('H:i'),
                'opening_time' => substr($restaurant->opening_time, 0, 5),
                'closing_time' => substr($restaurant->closing_time, 0, 5),
                'shift_total' => $restaurant->shift_total,
            ],
            'ingredients' => $ingredients,
            // The boosts that can be activated, and those active now
            'boosts' => Boost::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get()
            ->map(fn (Boost $boost) => [
                'uuid' => $boost->uuid,
                'name' => $boost->name,
                'target' => $boost->target,
                'speed_bonus' => $boost->speed_bonus,
                'minutes_off' => $boost->minutes_off,
                'quality_bonus' => $boost->quality_bonus,
                'duration' => $boost->duration,
            ])->values()->all(),
            'active_boosts' => $this->boostService->describeActive($restaurant, $now),
            // Conversions that can be done in the prep area, with how much of the input is stored
            'conversions' => Conversion::where('restaurant_uuid', $restaurant->uuid)->with(['Input', 'Output'])->get()
                ->map(function (Conversion $conversion) use ($ingredients) {
                    $input = collect($ingredients)->firstWhere('uuid', $conversion->input_uuid);
                    return [
                        'uuid' => $conversion->uuid,
                        'input_name' => $conversion->Input->name,
                        'output_name' => $conversion->Output->name,
                        'output_amount' => $conversion->output_amount,
                        'minutes' => $conversion->minutes,
                        'available' => $input !== null ? round($input['stored'] + $input['pile'], 2) : 0,
                    ];
                })
                ->sortBy(fn (array $conversion) => [$conversion['input_name'], $conversion['output_name']])->values()->all(),
            'recipes' => Recipe::where('restaurant_uuid', $restaurant->uuid)->with(['RecipeCategories', 'RecipeEquipment', 'RecipeIngredients'])->orderBy('name')->get()
                ->map(fn (Recipe $recipe) => [
                    'uuid' => $recipe->uuid,
                    // The base recipe a variant is of, which it is listed under
                    'parent_uuid' => $recipe->parent_uuid,
                    'name' => $recipe->name,
                    // What it is ordered at, which is its sale price while it is on sale
                    'price' => $sales[$recipe->uuid] ?? $recipe->price,
                    'base_price' => $recipe->price,
                    'on_sale' => isset($sales[$recipe->uuid]),
                    'prep_time' => $recipe->prep_time,
                    'cooking_time' => $recipe->cooking_time,
                    // Ingredients taken by category, with the ingredients of that category to choose from
                    'categories' => $recipe->RecipeCategories->map(fn (RecipeCategory $link) => [
                        'category' => $link->category,
                        'amount' => $link->amount,
                        'options' => collect($ingredients)->where('category', $link->category)->map(fn (array $ingredient) => [
                            'uuid' => $ingredient['uuid'],
                            'name' => $ingredient['name'],
                            'available' => round($ingredient['stored'] + $ingredient['pile'], 2),
                            'quality' => $ingredient['quality'],
                        ])->values()->all(),
                    ])->values()->all(),
                    // Ingredients taken outright, with the variants of each that can be used instead
                    'ingredients' => $recipe->RecipeIngredients
                        ->map(fn (RecipeIngredient $link) => [$link, collect($ingredients)->firstWhere('uuid', $link->ingredient_uuid)])
                        ->filter(fn (array $pair) => $pair[1] !== null)
                        ->map(fn (array $pair) => [
                            'ingredient_uuid' => $pair[1]['uuid'],
                            'amount' => $pair[0]->amount,
                            'options' => collect([$pair[1]])
                                ->concat(collect($ingredients)->where('parent_uuid', $pair[1]['uuid']))
                                ->map(fn (array $ingredient) => [
                                    'uuid' => $ingredient['uuid'],
                                    'name' => $ingredient['name'],
                                    'available' => round($ingredient['stored'] + $ingredient['pile'], 2),
                                    'quality' => $ingredient['quality'],
                                ])->values()->all(),
                        ])->values()->all(),
                    // Equipment other than cookers, with the variants of it that can be used instead
                    'equipment' => $recipe->RecipeEquipment
                        ->map(fn (RecipeEquipment $link) => [$link, $equipment->firstWhere('uuid', $link->equipment_uuid)])
                        ->filter(fn (array $pair) => $pair[1] !== null && $pair[1]->category !== EquipmentCategory::COOKER)
                        ->map(fn (array $pair) => [
                            'equipment_uuid' => $pair[1]->uuid,
                            'stage' => $pair[0]->stage->value,
                            'amount' => $pair[0]->amount,
                            'options' => collect([$pair[1]])
                                ->concat($equipment->filter(fn (Equipment $piece) => $piece->parent_uuid === $pair[1]->uuid && $piece->category !== EquipmentCategory::COOKER))
                                ->map(fn (Equipment $piece) => ['uuid' => $piece->uuid, 'name' => $piece->name, 'clean' => $clean[$piece->uuid] ?? 0])
                                ->values()->all(),
                        ])->values()->all(),
                ])->values()->all(),
            // Every piece of cooker equipment is a cooker of its own, holding one meal at a time
            'cookers' => $equipment->filter(fn (Equipment $piece) => $piece->category === EquipmentCategory::COOKER)
                ->map(fn (Equipment $piece) => [
                    'uuid' => $piece->uuid,
                    'name' => $piece->name,
                    'slots' => $this->inventoryService->getTotalStock($piece),
                ])->values()->all(),
            'equipment' => $equipment->filter(fn (Equipment $piece) => $piece->category !== EquipmentCategory::COOKER)
                ->map(function (Equipment $piece) {
                    $total = $this->inventoryService->getTotalStock($piece);
                    return [
                        'uuid' => $piece->uuid,
                        'name' => $piece->name,
                        'category_label' => $piece->category->label(),
                        'cleaning_time' => $piece->cleaning_time,
                        'amount' => $total,
                        'in_use' => $piece->amount_in_use,
                        'dirty' => $piece->amount_dirty,
                        'clean' => $this->getCleanAmount($piece, $total),
                    ];
                })->values()->all(),
            'meals' => $meals->map(fn (Meal $meal) => $this->describeMeal($meal, $now))->values()->all(),
            // Plated portions waiting to be brought to a table
            'plates' => ServedMeal::where('restaurant_uuid', $restaurant->uuid)->whereNull('order_item_uuid')
                ->with('Recipe')->orderBy('created_at')->get()
                ->map(fn (ServedMeal $plate) => [
                    'uuid' => $plate->uuid,
                    'recipe_uuid' => $plate->recipe_uuid,
                    'name' => $plate->Recipe->name,
                    'quality' => $plate->quality,
                    'expires_in_minutes' => $plate->expires_at !== null ? $this->minutesUntil($now, $plate->expires_at) : null,
                    'spoiled' => $plate->expires_at !== null && $now->gte($plate->expires_at),
                ])->values()->all(),
        ];
    }

    //| Plating

    /**
     * Plates a portion of a cooked meal, putting the recipe's serving equipment in use. The portion keeps the meal's
     * quality, and spoils as the meal would once in the serving area. Plating the last portion finishes the meal,
     * which makes its containers dirty
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @return ServedMeal The plated portion
     * @throws InvalidArgumentException Thrown when the meal isn't cooked, or too little serving equipment is clean
     */
    public function plateMeal(Restaurant $restaurant, Meal $meal): ServedMeal
    {
        $this->settleFinishedMeals($restaurant);
        $meal->refresh();

        $status = $meal->getStatusAt($restaurant->getNow());
        if ($status !== Meal::STATUS_DONE && $status !== Meal::STATUS_SERVING) {
            throw new InvalidArgumentException('Only cooked meals can be plated');
        }

        return DB::transaction(function () use ($restaurant, $meal) {
            $this->useEquipment($restaurant, $meal->equipment ?? [], EquipmentStage::SERVING);

            $plate = new ServedMeal();
            $plate->restaurant_uuid = $restaurant->uuid;
            $plate->recipe_uuid = $meal->recipe_uuid;
            $plate->quality = $meal->getServedQuality();
            $plate->expires_at = $meal->status === Meal::STATUS_SERVING
                ? $meal->expires_at
                : $this->getServedExpiry($meal, $restaurant->getNow());
            $plate->equipment = array_values(array_map(
                fn (array $entry) => ['equipment_uuid' => $entry['equipment_uuid'], 'amount' => $entry['amount']],
                array_filter($meal->equipment ?? [], fn (array $entry) => $entry['stage'] === EquipmentStage::SERVING->value)
            ));
            $plate->save();

            $meal->portions_left = max(0, $meal->portions_left - 1);
            if ($meal->portions_left === 0) {
                // The last portion is gone, so whatever still held the meal can be washed
                $this->releaseEquipment($restaurant, $this->getEquipmentInUse($meal), dirty: true);
                $meal->delete();
            } else {
                $meal->save();
            }

            return $plate;
        });
    }

    /**
     * Throws away a plated portion that hasn't been brought to a table, making its serving equipment dirty
     * @param Restaurant $restaurant The restaurant
     * @param ServedMeal $plate The plated portion
     * @throws InvalidArgumentException Thrown when it has already been brought to a table
     */
    public function scrapPlate(Restaurant $restaurant, ServedMeal $plate): void
    {
        if ($plate->order_item_uuid !== null) {
            throw new InvalidArgumentException('This plate has already been served');
        }

        DB::transaction(function () use ($restaurant, $plate) {
            $this->releaseEquipment($restaurant, $plate->equipment ?? [], dirty: true);
            $plate->delete();
        });
    }

    //| Clock

    /**
     * Moves the restaurant's time forward, passing midnight into the next date if needed
     * @param Restaurant $restaurant The restaurant
     * @param int $minutes How many minutes to move forward
     */
    public function advanceTime(Restaurant $restaurant, int $minutes): void
    {
        $restaurant->setNow($restaurant->getNow()->addMinutes($minutes));
        $restaurant->save();
    }

    /**
     * Sets the restaurant's time on its current date
     * @param Restaurant $restaurant The restaurant
     * @param string $time The time as H:i
     */
    public function setTime(Restaurant $restaurant, string $time): void
    {
        $restaurant->setNow(Carbon::parse($restaurant->getNow()->format('Y-m-d') . ' ' . $time));
        $restaurant->save();
    }

    //| Meals

    /**
     * Plans a meal of a recipe, taking its ingredients from the stacks closest to expiring first, across the storage
     * spaces and the pile of ingredients brought in during service, and putting the equipment used for prepping it in use.
     * Ingredients the recipe takes by category are taken from the ingredient chosen for each
     * @param Restaurant $restaurant The restaurant
     * @param Recipe $recipe The recipe to make
     * @param array<string, Ingredient> $choices The ingredient chosen for each category the recipe takes, keyed by category
     * @param array<string, string> $substitutes The variants to use instead of the prep equipment the recipe takes, as
     *                                          the chosen equipment uuid keyed by the uuid of the recipe's equipment
     * @param array<string, string> $ingredientSubstitutes The variants to use instead of the ingredients the recipe
     *                                                    takes, as the chosen uuid keyed by the recipe's ingredient
     * @return Meal The planned meal
     * @throws InvalidArgumentException Thrown when a category has no fitting ingredient chosen, or too little of an
     *                                  ingredient is stored or of the prep equipment is clean, or a variant isn't one
     */
    public function planMeal(Restaurant $restaurant, Recipe $recipe, array $choices = [], array $substitutes = [], array $ingredientSubstitutes = []): Meal
    {
        return DB::transaction(function () use ($restaurant, $recipe, $choices, $substitutes, $ingredientSubstitutes) {
            // A snapshot, so changing the recipe later doesn't change which equipment this meal holds
            $equipment = $recipe->RecipeEquipment()->get()->map(fn (RecipeEquipment $link) => [
                'equipment_uuid' => $link->equipment_uuid,
                'amount' => $link->amount,
                'stage' => $link->stage->value,
                'container' => $link->container,
            ])->values()->all();

            $equipment = $this->substituteEquipment($restaurant, $equipment, EquipmentStage::PREP, $substitutes);
            $this->useEquipment($restaurant, $equipment, EquipmentStage::PREP);
            $consumed = [];

            // Each ingredient comes from the stacks closest to expiring first, of the variant chosen for it if any
            $used = [];
            foreach ($recipe->RecipeIngredients()->with('Ingredient')->get() as $requirement) {
                $ingredient = $this->getIngredientVariant($restaurant, $requirement->Ingredient, $ingredientSubstitutes[$requirement->ingredient_uuid] ?? null);
                $consumed = array_merge($consumed, $this->inventoryService->takeForCooking($restaurant, $ingredient, $requirement->amount));
                $used[$ingredient->uuid] = $ingredient->quality;
            }

            // Ingredients taken by category come from the ingredient chosen for it
            foreach ($recipe->RecipeCategories as $requirement) {
                $ingredient = $choices[$requirement->category] ?? null;
                if ($ingredient === null || $ingredient->category !== $requirement->category) {
                    throw new InvalidArgumentException('Choose which ' . $requirement->category . ' to use');
                }

                $consumed = array_merge($consumed, $this->inventoryService->takeForCooking($restaurant, $ingredient, $requirement->amount));
                $used[$ingredient->uuid] = $ingredient->quality;
            }

            $meal = new Meal();
            $meal->restaurant_uuid = $restaurant->uuid;
            $meal->recipe_uuid = $recipe->uuid;
            $meal->status = Meal::STATUS_PLANNED;
            $meal->portions_left = max(1, $recipe->portions);
            $meal->consumed = $consumed;
            $meal->equipment = $equipment;
            // Each ingredient used counts once, however much of it is used
            $meal->ingredient_quality = $used !== [] ? (int) round(array_sum($used) / count($used)) : 0;
            $meal->save();

            return $meal;
        });
    }

    /**
     * Gets the ingredient to use for one a recipe takes: the chosen variant of it, or the ingredient itself
     * @param Restaurant $restaurant The restaurant
     * @param Ingredient $ingredient The ingredient the recipe takes
     * @param string|null $chosen The uuid of the chosen variant, if any
     * @return Ingredient The ingredient to use
     * @throws InvalidArgumentException Thrown when the chosen ingredient isn't a variant of it
     */
    private function getIngredientVariant(Restaurant $restaurant, Ingredient $ingredient, ?string $chosen): Ingredient
    {
        if ($chosen === null || $chosen === '' || $chosen === $ingredient->uuid) {
            return $ingredient;
        }

        $variant = Ingredient::where('uuid', $chosen)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($variant === null || $variant->parent_uuid !== $ingredient->uuid) {
            throw new InvalidArgumentException($variant?->name . ' cannot be used instead of ' . $ingredient->name);
        }

        return $variant;
    }

    /**
     * Cancels or scraps a meal. Only a meal that is just planned gets its ingredients back and its equipment back
     * clean, once it has been prepared the ingredients are wasted and the equipment it still holds becomes dirty
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @return bool Whether the ingredients were refunded
     */
    public function cancelMeal(Restaurant $restaurant, Meal $meal): bool
    {
        return DB::transaction(function () use ($restaurant, $meal) {
            $refund = $meal->status === Meal::STATUS_PLANNED;
            if ($refund) {
                $this->refundIngredients($restaurant, $meal->consumed ?? []);
            }

            $this->releaseEquipment($restaurant, $this->getEquipmentInUse($meal), dirty: !$refund);
            // Equipment carried over from meals put in held those meals, so it needs washing either way
            $this->releaseEquipment($restaurant, $this->getCarriedEquipment($meal), dirty: true);
            $meal->delete();
            return $refund;
        });
    }

    /**
     * Finishes prepping a planned meal, once the prep area has worked through it. The prep equipment becomes dirty,
     * except containers, which hold the prepped meal until it cooks. A recipe without cooking time skips the cooker,
     * and is ready to serve straight away, unless it is still waiting on other meals to be put in
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @param Carbon|null $at When the prep finished, in the restaurant's time, or null for now
     * @throws InvalidArgumentException Thrown when the meal isn't planned
     */
    public function finishPrep(Restaurant $restaurant, Meal $meal, ?Carbon $at = null): void
    {
        if ($meal->status !== Meal::STATUS_PLANNED) {
            throw new InvalidArgumentException('Only planned meals can be prepped');
        }

        DB::transaction(function () use ($restaurant, $meal, $at) {
            $this->releaseEquipment($restaurant, $this->filterEquipment($meal, EquipmentStage::PREP, container: false), dirty: true);

            $meal->status = Meal::STATUS_PREPPED;
            $meal->save();

            $this->skipCookingIfReady($restaurant, $meal, $at ?? $restaurant->getNow());
        });
    }

    /**
     * Makes a prepped meal that needs no cooking ready to serve, once every other meal it takes has been put in
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @param Carbon $now The restaurant's current time
     */
    private function skipCookingIfReady(Restaurant $restaurant, Meal $meal, Carbon $now): void
    {
        if ($meal->status === Meal::STATUS_PREPPED && $meal->getRecipe()->cooking_time <= 0 && $this->getMissingMeals($meal) === []) {
            $this->skipCooking($restaurant, $meal, $now);
        }
    }

    /**
     * Makes a prepped meal that needs no cooking ready to serve. Its prep containers are emptied, as they would be
     * into a cooker, and it stays good as a served meal does
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The prepped meal
     * @param Carbon $now When it became ready, in the restaurant's time
     */
    private function skipCooking(Restaurant $restaurant, Meal $meal, Carbon $now): void
    {
        $this->releaseEquipment($restaurant, $this->filterEquipment($meal, EquipmentStage::PREP, container: true), dirty: true);
        // Equipment carried over for cooking won't be cooked in
        $this->releaseEquipment($restaurant, $this->getCarriedEquipment($meal), dirty: true);
        $meal->carried_equipment = null;

        $meal->expires_at = $now->copy()->addHours($meal->getRecipe()->good_for);
        $meal->expires_at = $this->getServedExpiry($meal, $now);
        $meal->status = Meal::STATUS_SERVING;
        $meal->save();
    }

    /**
     * Sets how well a finished meal turned out
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @param int $quality The quality
     * @throws InvalidArgumentException Thrown when the meal isn't finished yet
     */
    public function setQuality(Restaurant $restaurant, Meal $meal, int $quality): void
    {
        if (!$meal->isFinishedAt($restaurant->getNow())) {
            throw new InvalidArgumentException('Only finished meals have a quality');
        }

        $meal->quality = $quality;
        $meal->save();
    }

    /**
     * Puts a prepped meal in a cooker, which starts cooking it at the speed of the cooker and its cooking equipment.
     * Once cooked, it stays good for the recipe's good for time
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @param Equipment $cooker The cooker equipment
     * @param int $slot Which piece of the cooker equipment to use, from 1
     * @param array<string, string> $substitutes The variants to use instead of the cooking equipment the recipe takes,
     *                                          as the chosen equipment uuid keyed by the uuid of the recipe's equipment
     * @throws InvalidArgumentException Thrown when the meal isn't prepped, still waits on other meals to be put in,
     *                                  the cooker doesn't exist or is full, or a variant isn't one
     */
    public function cookMeal(Restaurant $restaurant, Meal $meal, Equipment $cooker, int $slot, array $substitutes = []): void
    {
        if ($meal->status !== Meal::STATUS_PREPPED) {
            throw new InvalidArgumentException('Only prepped meals can be cooked');
        }

        $missing = $this->getMissingMeals($meal);
        if ($missing !== []) {
            throw new InvalidArgumentException(sprintf('%s still needs %s', $meal->getRecipe()->name, $missing[0]['name']));
        }

        if ($cooker->category !== EquipmentCategory::COOKER) {
            throw new InvalidArgumentException($cooker->name . ' is not a cooker');
        }

        if ($slot < 1 || $slot > $this->inventoryService->getTotalStock($cooker)) {
            throw new InvalidArgumentException('There is no ' . $cooker->name . ' ' . $slot);
        }

        DB::transaction(function () use ($restaurant, $meal, $cooker, $slot, $substitutes) {
            $full = Meal::where('cooker_uuid', $cooker->uuid)
                ->where('cooker_slot', $slot)
                ->where('status', Meal::STATUS_COOKING)
                ->lockForUpdate()
                ->exists();

            if ($full) {
                throw new InvalidArgumentException('That ' . $cooker->name . ' is already in use');
            }

            $meal->equipment = $this->substituteEquipment($restaurant, $meal->equipment ?? [], EquipmentStage::COOKING, $substitutes, $meal->carried_equipment ?? []);

            // The cooking equipment goes in use, apart from what was carried over and is in use already, and prep
            // containers are emptied into the cooker and become dirty
            $this->useEquipment($restaurant, $this->withoutCarriedEquipment($meal), EquipmentStage::COOKING);
            $meal->carried_equipment = null;
            $this->releaseEquipment($restaurant, $this->filterEquipment($meal, EquipmentStage::PREP, container: true), dirty: true);

            // Cooking picks up from any progress boosts made while it was prepped, at the speed of the cooker and the
            // cooking equipment
            $meal->status = Meal::STATUS_COOKING;
            $meal->cooker_uuid = $cooker->uuid;
            $meal->cooker_slot = $slot;
            $this->boostService->reschedule($meal, $restaurant->getNow());
        });
    }

    /**
     * Gets how many minutes are left until a cooking meal is done
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @return int The minutes left, rounded up
     * @throws InvalidArgumentException Thrown when the meal isn't cooking
     */
    public function getMinutesUntilDone(Restaurant $restaurant, Meal $meal): int
    {
        $now = $restaurant->getNow();
        if ($meal->getStatusAt($now) !== Meal::STATUS_COOKING) {
            throw new InvalidArgumentException('Only meals that are still cooking can be skipped');
        }

        return $this->minutesUntil($now, $meal->cooking_ends_at);
    }

    /**
     * Takes a done meal out of its cooker to be served. Served meals only stay fresh for the recipe's fresh for
     * time, unless they would have spoiled sooner anyway
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @throws InvalidArgumentException Thrown when the meal isn't done
     */
    public function serveMeal(Restaurant $restaurant, Meal $meal): void
    {
        // Containers used for cooking hold the meal until it is gone, the rest is released once cooking finished
        $this->settleFinishedMeals($restaurant);
        $meal->refresh();

        $now = $restaurant->getNow();
        if ($meal->getStatusAt($now) !== Meal::STATUS_DONE) {
            throw new InvalidArgumentException('Only meals that are done cooking can be served');
        }

        $meal->status = Meal::STATUS_SERVING;
        $meal->cooker_uuid = null;
        $meal->cooker_slot = null;
        $meal->expires_at = $this->getServedExpiry($meal, $now);
        $meal->save();
    }

    /**
     * Gets when a meal spoils once it is in the serving area: after the recipe's fresh for time from now, unless it
     * would have spoiled sooner anyway
     * @param Meal $meal The meal
     * @param Carbon $now The restaurant's current time
     * @return Carbon When it spoils
     */
    private function getServedExpiry(Meal $meal, Carbon $now): Carbon
    {
        $freshUntil = $now->copy()->addMinutes($meal->getRecipe()->fresh_for);
        return $meal->expires_at !== null && $meal->expires_at->lt($freshUntil) ? $meal->expires_at->copy() : $freshUntil;
    }

    //| Meals in meals

    /**
     * Puts a finished meal into a meal being prepared whose recipe takes it, covering all the portions still missing
     * for that requirement. Any portions of the finished meal left over are plated. Equipment the finished meal still
     * holds that the receiving meal uses as well stays in use for it, rather than being washed
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal being prepared
     * @param Meal $source The finished meal to put in
     * @param bool $plateRemaining Whether leftover portions of the finished meal may be plated
     * @throws InvalidArgumentException Thrown when the meal isn't being prepared or doesn't take the finished meal, the
     *                                  finished meal isn't finished, has spoiled or has too few portions, or leftover
     *                                  portions weren't allowed to be plated
     */
    public function supplyMeal(Restaurant $restaurant, Meal $meal, Meal $source, bool $plateRemaining = false): void
    {
        $this->settleFinishedMeals($restaurant);
        $meal->refresh();
        $source->refresh();
        $now = $restaurant->getNow();

        if ($meal->uuid === $source->uuid) {
            throw new InvalidArgumentException('A meal can\'t be put into itself');
        }

        if ($meal->status !== Meal::STATUS_PLANNED && $meal->status !== Meal::STATUS_PREPPED) {
            throw new InvalidArgumentException('Meals can only be put into meals being prepared');
        }

        if (!$source->isFinishedAt($now)) {
            throw new InvalidArgumentException('Only finished meals can be put into another meal');
        }

        if ($source->isSpoiledAt($now)) {
            throw new InvalidArgumentException('Spoiled meals can\'t be put into another meal');
        }

        $sourceRecipe = $source->getRecipe();
        $requirement = collect($this->getMissingMeals($meal))->first(fn (array $missing) => $missing['requirement']->accepts($sourceRecipe));
        if ($requirement === null) {
            throw new InvalidArgumentException(sprintf('%s doesn\'t need %s', $meal->getRecipe()->name, $sourceRecipe->name));
        }

        $needed = $requirement['portions'];
        if ($source->portions_left < $needed) {
            throw new InvalidArgumentException(sprintf('Not enough %s: %d portions needed, %d left', $sourceRecipe->name, $needed, $source->portions_left));
        }

        $remaining = $source->portions_left - $needed;
        if ($remaining > 0 && !$plateRemaining) {
            throw new InvalidArgumentException(sprintf('The %d portions of %s left over need to be plated', $remaining, $sourceRecipe->name));
        }

        DB::transaction(function () use ($restaurant, $meal, $source, $requirement, $needed, $remaining, $now) {
            $this->carryEquipment($restaurant, $source, $meal);

            $supplied = $meal->supplied ?? [];
            $supplied[$requirement['requirement']->uuid] = ($supplied[$requirement['requirement']->uuid] ?? 0) + $needed;
            $meal->supplied = $supplied;
            $meal->save();

            $source->portions_left = $remaining;
            $source->save();

            if ($remaining > 0) {
                // Plating the last portion finishes the meal, washing what still held it
                for ($portion = 0; $portion < $remaining; $portion++) {
                    $this->plateMeal($restaurant, $source);
                }
            } else {
                $this->releaseEquipment($restaurant, $this->getEquipmentInUse($source), dirty: true);
                $source->delete();
            }

            $this->skipCookingIfReady($restaurant, $meal, $now);
        });
    }

    /**
     * Gets the other meals a meal still waits on to be put in, per requirement of its recipe
     * @param Meal $meal The meal
     * @return array The missing meals, each with the requirement, its label and how many portions are still missing
     */
    private function getMissingMeals(Meal $meal): array
    {
        $supplied = $meal->supplied ?? [];
        $missing = [];

        // Specific recipes are matched before categories, so a meal fulfils the most fitting requirement
        $requirements = $meal->Recipe->RecipeMeals->sortBy(fn (RecipeMeal $requirement) => $requirement->required_recipe_uuid === null);
        foreach ($requirements as $requirement) {
            $portions = $requirement->portions - ($supplied[$requirement->uuid] ?? 0);
            if ($portions > 0) {
                $missing[] = ['requirement' => $requirement, 'name' => $requirement->getLabel(), 'portions' => $portions];
            }
        }

        return $missing;
    }

    /**
     * Hands the equipment a finished meal holds over to the meal it is put into, as far as that meal uses the same
     * equipment. Pieces stand in for those the receiving meal already holds, which go back clean, and otherwise are
     * kept in use for its cooking. The pieces handed over are taken off the finished meal, so they aren't washed with it
     * @param Restaurant $restaurant The restaurant
     * @param Meal $source The finished meal
     * @param Meal $meal The meal it is put into
     */
    private function carryEquipment(Restaurant $restaurant, Meal $source, Meal $meal): void
    {
        $held = $this->sumEquipment($this->getEquipmentInUse($meal));
        $cooking = $this->sumEquipment(array_filter($meal->equipment ?? [], fn (array $entry) => $entry['stage'] === EquipmentStage::COOKING->value));
        $carried = $meal->carried_equipment ?? [];
        $sourceEquipment = $source->equipment ?? [];

        foreach ($this->sumEquipment($this->getEquipmentInUse($source)) as $uuid => $amount) {
            $swapped = min($amount, $held[$uuid] ?? 0);
            $kept = min($amount - $swapped, max(0, ($cooking[$uuid] ?? 0) - ($carried[$uuid] ?? 0)));

            if ($swapped > 0) {
                $this->releaseEquipment($restaurant, [['equipment_uuid' => $uuid, 'amount' => $swapped]], dirty: false);
            }
            if ($kept > 0) {
                $carried[$uuid] = ($carried[$uuid] ?? 0) + $kept;
            }

            // Whatever was handed over no longer belongs to the finished meal
            $handed = $swapped + $kept;
            foreach ($sourceEquipment as $index => $entry) {
                if ($handed <= 0) {
                    break;
                }
                if ($entry['equipment_uuid'] === $uuid && $this->holdsEquipment($source, $entry)) {
                    $taken = min($entry['amount'], $handed);
                    $sourceEquipment[$index]['amount'] -= $taken;
                    $handed -= $taken;
                }
            }
        }

        $meal->carried_equipment = $carried !== [] ? $carried : null;
        $source->equipment = $sourceEquipment;
    }

    /**
     * Gets the equipment carried over to a meal from meals put into it, which is kept in use for its cooking
     * @param Meal $meal The meal
     * @return array The equipment, as equipment_uuid and amount
     */
    private function getCarriedEquipment(Meal $meal): array
    {
        return array_map(fn (string $uuid, int $amount) => ['equipment_uuid' => $uuid, 'amount' => $amount],
            array_keys($meal->carried_equipment ?? []), array_values($meal->carried_equipment ?? []));
    }

    /**
     * Gets a meal's equipment without the cooking equipment carried over to it, which is in use already
     * @param Meal $meal The meal
     * @return array The equipment, as equipment_uuid, amount, stage and container
     */
    private function withoutCarriedEquipment(Meal $meal): array
    {
        $carried = $meal->carried_equipment ?? [];
        $entries = [];
        foreach ($meal->equipment ?? [] as $entry) {
            if ($entry['stage'] === EquipmentStage::COOKING->value && ($carried[$entry['equipment_uuid']] ?? 0) > 0) {
                $covered = min($entry['amount'], $carried[$entry['equipment_uuid']]);
                $carried[$entry['equipment_uuid']] -= $covered;
                $entry['amount'] -= $covered;
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Swaps the equipment a meal takes in a stage for the chosen variants of it. What was carried over from meals put
     * in stays as it is, since that is in use already
     * @param Restaurant $restaurant The restaurant
     * @param array $entries The meal's equipment, as equipment_uuid, amount, stage and container
     * @param EquipmentStage $stage The stage whose equipment is swapped
     * @param array<string, string> $substitutes The chosen equipment uuid, keyed by the uuid of the equipment it replaces
     * @param array<string, int> $carried The amounts carried over, keyed by equipment uuid
     * @return array The equipment, as equipment_uuid, amount, stage and container
     * @throws InvalidArgumentException Thrown when a chosen piece isn't a variant of the equipment it replaces
     */
    private function substituteEquipment(Restaurant $restaurant, array $entries, EquipmentStage $stage, array $substitutes, array $carried = []): array
    {
        $result = [];
        foreach ($entries as $entry) {
            if ($entry['stage'] !== $stage->value) {
                $result[] = $entry;
                continue;
            }

            // Carried over equipment is matched in the same order withoutCarriedEquipment matches it
            $base = $entry['equipment_uuid'];
            $covered = min($entry['amount'], $carried[$base] ?? 0);
            $carried[$base] = ($carried[$base] ?? 0) - $covered;

            $chosen = $substitutes[$base] ?? null;
            if ($chosen === null || $chosen === '' || $chosen === $base || $entry['amount'] <= $covered) {
                $result[] = $entry;
                continue;
            }

            $variant = Equipment::where('uuid', $chosen)->where('restaurant_uuid', $restaurant->uuid)->first();
            if ($variant === null || $variant->parent_uuid !== $base || $variant->category === EquipmentCategory::COOKER) {
                throw new InvalidArgumentException('That equipment cannot be used instead');
            }

            if ($covered > 0) {
                $result[] = array_merge($entry, ['amount' => $covered]);
            }
            $result[] = array_merge($entry, ['equipment_uuid' => $variant->uuid, 'amount' => $entry['amount'] - $covered]);
        }

        return $result;
    }

    /**
     * Adds up equipment entries per piece of equipment
     * @param array $entries The equipment, as equipment_uuid and amount
     * @return array<string, int> The amounts keyed by equipment uuid
     */
    private function sumEquipment(array $entries): array
    {
        $amounts = [];
        foreach ($entries as $entry) {
            $amounts[$entry['equipment_uuid']] = ($amounts[$entry['equipment_uuid']] ?? 0) + $entry['amount'];
        }

        return $amounts;
    }

    //| Equipment

    /**
     * Gets how many pieces of a piece of equipment are clean: those owned or stored that are neither dirty nor in use
     * @param Equipment $equipment The equipment
     * @param int|null $total How many pieces are owned or stored, when already known
     * @return int How many pieces are clean
     */
    private function getCleanAmount(Equipment $equipment, ?int $total = null): int
    {
        $total ??= $this->inventoryService->getTotalStock($equipment);
        return max(0, $total - $equipment->amount_in_use - $equipment->amount_dirty);
    }

    /**
     * Puts a meal's equipment for a stage in use, checking first that enough of all of it is clean
     * @param Restaurant $restaurant The restaurant
     * @param array $entries The meal's equipment, as equipment_uuid, amount, stage and container
     * @param EquipmentStage $stage The stage whose equipment goes in use
     * @throws InvalidArgumentException Thrown when too little of a piece of equipment is clean
     */
    public function useEquipment(Restaurant $restaurant, array $entries, EquipmentStage $stage): void
    {
        $needed = [];
        foreach ($entries as $entry) {
            if ($entry['stage'] === $stage->value) {
                $needed[$entry['equipment_uuid']] = ($needed[$entry['equipment_uuid']] ?? 0) + $entry['amount'];
            }
        }

        // Every piece is checked before any goes in use, so a shortage leaves everything as it was
        $pieces = [];
        foreach ($needed as $uuid => $amount) {
            $equipment = Equipment::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->lockForUpdate()->first();
            if ($equipment === null) {
                continue;
            }

            $clean = $this->getCleanAmount($equipment);
            if ($clean < $amount) {
                throw new InvalidArgumentException(sprintf('Not enough clean %s: %d needed, %d clean', $equipment->name, $amount, $clean));
            }

            $pieces[] = [$equipment, $amount];
        }

        foreach ($pieces as [$equipment, $amount]) {
            $equipment->amount_in_use += $amount;
            $equipment->save();
        }
    }

    /**
     * Takes equipment out of use, either back to clean or as dirty
     * @param Restaurant $restaurant The restaurant
     * @param array $entries The equipment, as equipment_uuid and amount
     * @param bool $dirty Whether it was used and is now dirty, rather than returned unused
     */
    public function releaseEquipment(Restaurant $restaurant, array $entries, bool $dirty): void
    {
        foreach ($entries as $entry) {
            $equipment = Equipment::where('uuid', $entry['equipment_uuid'])->where('restaurant_uuid', $restaurant->uuid)->lockForUpdate()->first();
            if ($equipment === null) {
                continue;
            }

            $equipment->amount_in_use = max(0, $equipment->amount_in_use - $entry['amount']);
            if ($dirty) {
                $equipment->amount_dirty += $entry['amount'];
            }
            $equipment->save();
        }
    }

    /**
     * Gets a meal's equipment for a stage, either the containers or the rest
     * @param Meal $meal The meal
     * @param EquipmentStage $stage The stage
     * @param bool $container Whether to get the containers, rather than the equipment that isn't one
     * @return array The equipment, as equipment_uuid, amount, stage and container
     */
    private function filterEquipment(Meal $meal, EquipmentStage $stage, bool $container): array
    {
        return array_values(array_filter($meal->equipment ?? [],
            fn (array $entry) => $entry['stage'] === $stage->value && (bool) $entry['container'] === $container));
    }

    /**
     * Gets the equipment a meal currently holds. Prep equipment is held while planned, and prep containers until the
     * meal cooks. Cooking equipment is held while cooking, and cooking containers until the meal is gone
     * @param Meal $meal The meal
     * @return array The equipment, as equipment_uuid, amount, stage and container
     */
    private function getEquipmentInUse(Meal $meal): array
    {
        return array_values(array_filter($meal->equipment ?? [], fn (array $entry) => $this->holdsEquipment($meal, $entry)));
    }

    /**
     * Checks whether a meal currently holds an entry of its equipment, as described by getEquipmentInUse
     * @param Meal $meal The meal
     * @param array $entry The equipment entry, as equipment_uuid, amount, stage and container
     * @return bool Whether the meal holds it
     */
    private function holdsEquipment(Meal $meal, array $entry): bool
    {
        $cooking = $meal->status === Meal::STATUS_COOKING;
        $cooked = $cooking || $meal->status === Meal::STATUS_SERVING;

        return match ($entry['stage']) {
            EquipmentStage::PREP->value => $meal->status === Meal::STATUS_PLANNED || ($meal->status === Meal::STATUS_PREPPED && $entry['container']),
            EquipmentStage::COOKING->value => ($cooking && !$meal->cooking_settled) || ($cooked && $entry['container']),
            default => false,
        };
    }

    /**
     * Releases the cooking equipment of meals that have finished cooking, which becomes dirty. Containers stay in use,
     * as they hold the meal until it is gone. As whether a meal is done follows from the clock, this catches up after
     * the clock moved
     * @param Restaurant $restaurant The restaurant
     */
    public function settleFinishedMeals(Restaurant $restaurant): void
    {
        $finished = Meal::where('restaurant_uuid', $restaurant->uuid)
            ->where('status', Meal::STATUS_COOKING)
            ->where('cooking_settled', false)
            ->where('cooking_ends_at', '<=', $restaurant->getNow())
            ->get();

        foreach ($finished as $meal) {
            DB::transaction(function () use ($restaurant, $meal) {
                $this->releaseEquipment($restaurant, $this->filterEquipment($meal, EquipmentStage::COOKING, container: false), dirty: true);

                $meal->cooking_settled = true;
                $meal->save();
            });
        }
    }

    //| Helpers

    /**
     * Describes a meal as shown in the kitchen, with its timers relative to the restaurant's current time
     * @param Meal $meal The meal
     * @param Carbon $now The restaurant's current time
     * @return array The meal
     */
    private function describeMeal(Meal $meal, Carbon $now): array
    {
        $status = $meal->getStatusAt($now);

        return [
            'uuid' => $meal->uuid,
            'recipe_uuid' => $meal->recipe_uuid,
            'name' => $meal->Recipe->name,
            // What other recipes can take this meal by
            'recipe_category' => $meal->Recipe->category,
            'status' => $status,
            'prep_time' => $meal->Recipe->prep_time,
            'cooker_uuid' => $meal->cooker_uuid,
            'cooker_slot' => $meal->cooker_slot,
            'cooking_minutes_left' => $status === Meal::STATUS_COOKING ? $this->minutesUntil($now, $meal->cooking_ends_at) : null,
            'cooking_progress' => $status === Meal::STATUS_COOKING ? $this->getCookingProgress($meal) : null,
            // How fast it is being worked on, where 1 is normal, while it is prepped or cooking
            'speed' => $this->getMealSpeed($meal, $status, $now),
            'expires_in_minutes' => $meal->expires_at !== null ? $this->minutesUntil($now, $meal->expires_at) : null,
            'spoiled' => $meal->isSpoiledAt($now),
            'portions_left' => $meal->portions_left,
            // The quality it is served at, with what boosts added, of which the bonus is part
            'quality' => $meal->getServedQuality(),
            'quality_bonus' => $meal->quality_bonus,
            'ingredient_quality' => $meal->ingredient_quality,
            // A finished meal without a quality yet asks for it
            'finished' => $meal->isFinishedAt($now),
            // Other meals still to be put in while it is being prepared, with how many portions of each
            'missing_meals' => in_array($meal->status, [Meal::STATUS_PLANNED, Meal::STATUS_PREPPED], true)
                ? array_map(fn (array $missing) => [
                    'recipe_uuid' => $missing['requirement']->required_recipe_uuid,
                    'category' => $missing['requirement']->category,
                    'name' => $missing['name'],
                    'portions' => $missing['portions'],
                ], $this->getMissingMeals($meal))
                : [],
        ];
    }

    /**
     * Gets how far along a cooking meal is, from 0 when it went in to 1 when it is done
     */
    private function getCookingProgress(Meal $meal): float
    {
        $total = $meal->Recipe->cooking_time;
        if ($total <= 0) {
            return 1.0;
        }

        return round(min(1, max(0, $meal->cooking_progress / $total)), 4);
    }

    /**
     * Gets how fast a meal is worked on in the stage it is in: prep while planned, cooking once prepped
     * @return float|null The speed, where 1 is normal, or null when it isn't being worked on
     */
    private function getMealSpeed(Meal $meal, string $status, Carbon $now): ?float
    {
        $stage = match ($status) {
            Meal::STATUS_PLANNED => EquipmentStage::PREP,
            Meal::STATUS_PREPPED, Meal::STATUS_COOKING => EquipmentStage::COOKING,
            default => null,
        };

        return $stage !== null ? $this->boostService->getMealSpeed($meal, $stage, $now, $this->boostService->getBoosts($meal->getRestaurant(), $now)) : null;
    }

    /**
     * Describes the restaurant's ingredients for the service panel: how much of each is in the storage spaces, and
     * how much is in the pile of ingredients brought in during service
     * @param Restaurant $restaurant The restaurant
     * @return array The ingredients
     */
    private function describeIngredients(Restaurant $restaurant): array
    {
        $stored = StorageIngredient::query()
            ->join(Storage::TABLE_NAME, Storage::TABLE_NAME . '.uuid', '=', StorageIngredient::TABLE_NAME . '.storage_uuid')
            ->where(Storage::TABLE_NAME . '.restaurant_uuid', $restaurant->uuid)
            ->groupBy(StorageIngredient::TABLE_NAME . '.ingredient_uuid')
            ->selectRaw(StorageIngredient::TABLE_NAME . '.ingredient_uuid, SUM(' . StorageIngredient::TABLE_NAME . '.amount) as amount')
            ->pluck('amount', 'ingredient_uuid');
        $pile = PileIngredient::where('restaurant_uuid', $restaurant->uuid)->pluck('amount', 'ingredient_uuid');

        return Ingredient::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get()
            ->map(fn (Ingredient $ingredient) => [
                'uuid' => $ingredient->uuid,
                'name' => $ingredient->name,
                'category' => $ingredient->category,
                'parent_uuid' => $ingredient->parent_uuid,
                'quality' => $ingredient->quality,
                'icon' => $ingredient->icon !== null ? asset('img/' . $ingredient->icon) : null,
                'stored' => round((float) ($stored[$ingredient->uuid] ?? 0), 2),
                'pile' => round((float) ($pile[$ingredient->uuid] ?? 0), 2),
            ])->values()->all();
    }

    /**
     * Gets how many minutes are left until a moment, rounded up and never below zero
     */
    private function minutesUntil(Carbon $now, Carbon $moment): int
    {
        return max(0, (int) ceil(($moment->getTimestamp() - $now->getTimestamp()) / 60));
    }

    /**
     * Puts consumed ingredients back where they were taken from, with the days they had left
     * @param Restaurant $restaurant The restaurant
     * @param array $consumed The consumed ingredients, as given by InventoryService::takeForCooking
     */
    private function refundIngredients(Restaurant $restaurant, array $consumed): void
    {
        $this->inventoryService->putBack($restaurant, $consumed);
    }
}
