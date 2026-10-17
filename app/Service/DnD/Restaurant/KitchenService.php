<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\RecipeMeal;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The meals made in the kitchen, from being planned through prep and cooking to being plated. A meal takes its
 * ingredients when planned, and holds the equipment of each stage while it is in that stage
 */
class KitchenService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly BoostService $boostService,
        private readonly EquipmentService $equipmentService,
    ) {
    }

    //| Planning

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

            $equipment = $this->equipmentService->substituteEquipment($restaurant, $equipment, EquipmentStage::PREP, $substitutes);
            $this->equipmentService->useEquipment($restaurant, $equipment, EquipmentStage::PREP);
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
                $this->inventoryService->putBack($restaurant, $meal->consumed ?? []);
            }

            $this->equipmentService->releaseEquipment($restaurant, $this->equipmentService->getEquipmentInUse($meal), dirty: !$refund);
            // Equipment carried over from meals put in held those meals, so it needs washing either way
            $this->equipmentService->releaseEquipment($restaurant, $this->equipmentService->getCarriedEquipment($meal), dirty: true);
            $meal->delete();
            return $refund;
        });
    }

    //| Prep & cooking

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
            $this->equipmentService->releaseEquipment($restaurant, $this->equipmentService->filterEquipment($meal, EquipmentStage::PREP, container: false), dirty: true);

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
        $this->equipmentService->releaseEquipment($restaurant, $this->equipmentService->filterEquipment($meal, EquipmentStage::PREP, container: true), dirty: true);
        // Equipment carried over for cooking won't be cooked in
        $this->equipmentService->releaseEquipment($restaurant, $this->equipmentService->getCarriedEquipment($meal), dirty: true);
        $meal->carried_equipment = null;

        // It stays good for the recipe's good for time, and only fresh for its fresh for time within that
        $meal->expires_at = $now->copy()->addHours($meal->getRecipe()->good_for);
        $meal->expires_at = $this->getServedExpiry($meal, $now);
        $meal->status = Meal::STATUS_SERVING;
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

            $meal->equipment = $this->equipmentService->substituteEquipment($restaurant, $meal->equipment ?? [], EquipmentStage::COOKING, $substitutes, $meal->carried_equipment ?? []);

            // The cooking equipment goes in use, apart from what was carried over and is in use already, and prep
            // containers are emptied into the cooker and become dirty
            $this->equipmentService->useEquipment($restaurant, $this->equipmentService->withoutCarriedEquipment($meal), EquipmentStage::COOKING);
            $meal->carried_equipment = null;
            $this->equipmentService->releaseEquipment($restaurant, $this->equipmentService->filterEquipment($meal, EquipmentStage::PREP, container: true), dirty: true);

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

        return max(0, (int) ceil(($meal->cooking_ends_at->getTimestamp() - $now->getTimestamp()) / 60));
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
                $this->equipmentService->releaseEquipment($restaurant, $this->equipmentService->filterEquipment($meal, EquipmentStage::COOKING, container: false), dirty: true);

                $meal->cooking_settled = true;
                $meal->save();
            });
        }
    }

    //| Serving

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
     * Gets when a meal spoils once it is in the serving area: after the recipe's fresh for time from now, with what
     * boosts added to it, unless it would have spoiled sooner anyway
     * @param Meal $meal The meal
     * @param Carbon $now The restaurant's current time
     * @return Carbon When it spoils
     */
    private function getServedExpiry(Meal $meal, Carbon $now): Carbon
    {
        $freshUntil = $now->copy()->addMinutes(max(0, $meal->getRecipe()->fresh_for + $meal->fresh_for_bonus));
        return $meal->expires_at !== null && $meal->expires_at->lt($freshUntil) ? $meal->expires_at->copy() : $freshUntil;
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
            $this->equipmentService->useEquipment($restaurant, $meal->equipment ?? [], EquipmentStage::SERVING);

            $plate = new ServedMeal();
            $plate->restaurant_uuid = $restaurant->uuid;
            $plate->recipe_uuid = $meal->recipe_uuid;
            $plate->quality = $meal->getServedQuality();
            $plate->expires_at = $meal->status === Meal::STATUS_SERVING
                ? $meal->expires_at
                : $this->getServedExpiry($meal, $restaurant->getNow());
            $plate->equipment = array_map(
                fn (array $entry) => ['equipment_uuid' => $entry['equipment_uuid'], 'amount' => $entry['amount']],
                $this->equipmentService->filterEquipment($meal, EquipmentStage::SERVING)
            );
            $plate->save();

            $meal->portions_left = max(0, $meal->portions_left - 1);
            if ($meal->portions_left === 0) {
                $this->finishMeal($restaurant, $meal);
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
            $this->equipmentService->releaseEquipment($restaurant, $plate->equipment ?? [], dirty: true);
            $plate->delete();
        });
    }

    /**
     * Finishes a meal whose last portion is gone, so whatever still held it can be washed
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     */
    private function finishMeal(Restaurant $restaurant, Meal $meal): void
    {
        $this->equipmentService->releaseEquipment($restaurant, $this->equipmentService->getEquipmentInUse($meal), dirty: true);
        $meal->delete();
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
            $this->equipmentService->carryEquipment($restaurant, $source, $meal);

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
                $this->finishMeal($restaurant, $source);
            }

            $this->skipCookingIfReady($restaurant, $meal, $now);
        });
    }

    /**
     * Gets the other meals a meal still waits on to be put in, per requirement of its recipe
     * @param Meal $meal The meal
     * @return array The missing meals, each with the requirement, its label and how many portions are still missing
     */
    public function getMissingMeals(Meal $meal): array
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
}
