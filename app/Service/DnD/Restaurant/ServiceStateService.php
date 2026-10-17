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
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageIngredient;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the state of the restaurant's service as shown on the service page: its clock and kitchen, its staff and
 * work areas, and the orders being served
 */
class ServiceStateService
{
    public function __construct(
        private readonly KitchenService $kitchenService,
        private readonly EquipmentService $equipmentService,
        private readonly InventoryService $inventoryService,
        private readonly BoostService $boostService,
        private readonly WorkStateService $workStateService,
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * Gets the full service state: the kitchen, its staff and work areas, and the orders being served
     * @param Restaurant $restaurant The restaurant
     * @return array The service state
     */
    public function getState(Restaurant $restaurant): array
    {
        return $this->getKitchen($restaurant)
            + $this->workStateService->describe($restaurant)
            + ['orders' => $this->orderService->describeOrders($restaurant)]
            // How many changes the restaurant has seen, which a refresh sends along to learn whether anything changed
            + ['version' => $restaurant->state_version];
    }

    /**
     * Builds the state of the restaurant's kitchen: its clock, the cookers, the other equipment, the recipes that can
     * be planned and the meals being made
     * @param Restaurant $restaurant The restaurant to build the state for
     * @return array The kitchen state
     */
    private function getKitchen(Restaurant $restaurant): array
    {
        // Meals that finished cooking since the clock last moved release their cooking equipment first
        $this->kitchenService->settleFinishedMeals($restaurant);

        $now = $restaurant->getNow();
        $equipment = Equipment::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get();
        $meals = Meal::where('restaurant_uuid', $restaurant->uuid)->with('Recipe.RecipeMeals.RequiredRecipe')->orderBy('created_at')->get();
        $ingredients = $this->describeIngredients($restaurant);

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
            'boosts' => $this->describeBoosts($restaurant),
            'active_boosts' => $this->boostService->describeActive($restaurant, $now),
            'conversions' => $this->describeConversions($restaurant, $ingredients),
            'recipes' => $this->describeRecipes($restaurant, $ingredients, $equipment),
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
                        'clean' => $this->equipmentService->getCleanAmount($piece, $total),
                    ];
                })->values()->all(),
            'meals' => $meals->map(fn (Meal $meal) => $this->describeMeal($meal, $now))->values()->all(),
            'plates' => $this->describePlates($restaurant, $now),
        ];
    }

    //| Kitchen

    /**
     * Describes the boosts that can be activated
     * @param Restaurant $restaurant The restaurant
     * @return array The boosts
     */
    private function describeBoosts(Restaurant $restaurant): array
    {
        return Boost::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get()
            ->map(fn (Boost $boost) => [
                'uuid' => $boost->uuid,
                'name' => $boost->name,
                // What it can be activated on, of the Boost::TARGET constants
                'targets' => $boost->targets ?? [],
                'speed_bonus' => $boost->speed_bonus,
                'minutes_off' => $boost->minutes_off,
                'quality_bonus' => $boost->quality_bonus,
                'fresh_for_bonus' => $boost->fresh_for_bonus,
                'duration' => $boost->duration,
            ])->values()->all();
    }

    /**
     * Describes the conversions that can be done in the prep area, with how much of the input is stored
     * @param Restaurant $restaurant The restaurant
     * @param array $ingredients The ingredients, as given by describeIngredients
     * @return array The conversions
     */
    private function describeConversions(Restaurant $restaurant, array $ingredients): array
    {
        return Conversion::where('restaurant_uuid', $restaurant->uuid)->with(['Input', 'Output'])->get()
            ->map(function (Conversion $conversion) use ($ingredients) {
                $input = collect($ingredients)->firstWhere('uuid', $conversion->input_uuid);
                return [
                    'uuid' => $conversion->uuid,
                    'input_name' => $conversion->Input->name,
                    'output_name' => $conversion->Output->name,
                    'output_amount' => $conversion->output_amount,
                    'minutes' => $conversion->minutes,
                    'available' => $input !== null ? $this->getAvailable($input) : 0,
                ];
            })
            ->sortBy(fn (array $conversion) => [$conversion['input_name'], $conversion['output_name']])->values()->all();
    }

    /**
     * Describes the recipes that can be planned, with the ingredients and equipment to choose from for each
     * @param Restaurant $restaurant The restaurant
     * @param array $ingredients The ingredients, as given by describeIngredients
     * @param Collection<Equipment> $equipment The restaurant's equipment
     * @return array The recipes
     */
    private function describeRecipes(Restaurant $restaurant, array $ingredients, Collection $equipment): array
    {
        $sales = $restaurant->getSalePrices();
        $clean = $equipment->mapWithKeys(fn (Equipment $piece) => [$piece->uuid => $this->equipmentService->getCleanAmount($piece)]);

        return Recipe::where('restaurant_uuid', $restaurant->uuid)->with(['RecipeCategories', 'RecipeEquipment', 'RecipeIngredients'])->orderBy('name')->get()
            ->map(fn (Recipe $recipe) => [
                'uuid' => $recipe->uuid,
                // The base recipe a variant is of, which it is listed under
                'parent_uuid' => $recipe->parent_uuid,
                'name' => $recipe->name,
                // What kind of recipe it is, such as a meal or drink, which the menu is split by
                'type' => $recipe->type,
                'category' => $recipe->category,
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
                    'options' => collect($ingredients)->where('category', $link->category)
                        ->map(fn (array $ingredient) => $this->describeIngredientOption($ingredient))->values()->all(),
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
                            ->map(fn (array $ingredient) => $this->describeIngredientOption($ingredient))->values()->all(),
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
            ])->values()->all();
    }

    /**
     * Describes an ingredient as an option a recipe can be made with
     * @param array $ingredient The ingredient, as given by describeIngredients
     * @return array The option
     */
    private function describeIngredientOption(array $ingredient): array
    {
        return [
            'uuid' => $ingredient['uuid'],
            'name' => $ingredient['name'],
            'available' => $this->getAvailable($ingredient),
            'quality' => $ingredient['quality'],
        ];
    }

    /**
     * Gets how much of an ingredient can be used: what is stored, and what is in the pile
     * @param array $ingredient The ingredient, as given by describeIngredients
     * @return float The amount
     */
    private function getAvailable(array $ingredient): float
    {
        return round($ingredient['stored'] + $ingredient['pile'], 2);
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

    //| Meals & plates

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
                ], $this->kitchenService->getMissingMeals($meal))
                : [],
        ];
    }

    /**
     * Gets how far along a cooking meal is, from 0 when it went in to 1 when it is done
     * @param Meal $meal The meal
     * @return float The progress
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
     * @param Meal $meal The meal
     * @param string $status The meal's status now
     * @param Carbon $now The restaurant's current time
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
     * Describes the plated portions waiting to be brought to a table
     * @param Restaurant $restaurant The restaurant
     * @param Carbon $now The restaurant's current time
     * @return array The plated portions
     */
    private function describePlates(Restaurant $restaurant, Carbon $now): array
    {
        return ServedMeal::where('restaurant_uuid', $restaurant->uuid)->whereNull('order_item_uuid')
            ->with('Recipe')->orderBy('created_at')->get()
            ->map(fn (ServedMeal $plate) => [
                'uuid' => $plate->uuid,
                'recipe_uuid' => $plate->recipe_uuid,
                'name' => $plate->Recipe->name,
                'quality' => $plate->quality,
                'expires_in_minutes' => $plate->expires_at !== null ? $this->minutesUntil($now, $plate->expires_at) : null,
                'spoiled' => $plate->expires_at !== null && $now->gte($plate->expires_at),
            ])->values()->all();
    }

    /**
     * Gets how many minutes are left until a moment, rounded for showing
     * @param Carbon $now The restaurant's current time
     * @param Carbon $moment The moment
     * @return int The minutes left
     */
    private function minutesUntil(Carbon $now, Carbon $moment): int
    {
        return Minutes::until($now, $moment);
    }
}
