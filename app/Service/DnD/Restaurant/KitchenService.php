<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\PileIngredient;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageIngredient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KitchenService
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
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
        $meals = Meal::where('restaurant_uuid', $restaurant->uuid)->with('Recipe')->orderBy('created_at')->get();
        $sales = $restaurant->getSalePrices();

        return [
            'clock' => [
                'date' => $now->format('Y-m-d'),
                'time' => $now->format('H:i'),
                'opening_time' => substr($restaurant->opening_time, 0, 5),
                'closing_time' => substr($restaurant->closing_time, 0, 5),
                'shift_total' => $restaurant->shift_total,
            ],
            'ingredients' => $this->describeIngredients($restaurant),
            'recipes' => Recipe::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get()
                ->map(fn (Recipe $recipe) => [
                    'uuid' => $recipe->uuid,
                    'name' => $recipe->name,
                    // What it is ordered at, which is its sale price while it is on sale
                    'price' => $sales[$recipe->uuid] ?? $recipe->price,
                    'base_price' => $recipe->price,
                    'on_sale' => isset($sales[$recipe->uuid]),
                    'prep_time' => $recipe->prep_time,
                    'cooking_time' => $recipe->cooking_time,
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
            $plate->quality = $meal->quality;
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
     * Plans a meal of a recipe, taking its ingredients out of the restaurant's storage spaces in their order, then
     * out of the pile of ingredients brought in during service, and putting the equipment used for prepping it in use
     * @param Restaurant $restaurant The restaurant
     * @param Recipe $recipe The recipe to make
     * @return Meal The planned meal
     * @throws InvalidArgumentException Thrown when too little of an ingredient is stored or of the prep equipment is clean
     */
    public function planMeal(Restaurant $restaurant, Recipe $recipe): Meal
    {
        return DB::transaction(function () use ($restaurant, $recipe) {
            // A snapshot, so changing the recipe later doesn't change which equipment this meal holds
            $equipment = $recipe->RecipeEquipment()->get()->map(fn (RecipeEquipment $link) => [
                'equipment_uuid' => $link->equipment_uuid,
                'amount' => $link->amount,
                'stage' => $link->stage->value,
                'container' => $link->container,
            ])->values()->all();

            $this->useEquipment($restaurant, $equipment, EquipmentStage::PREP);
            $consumed = [];

            foreach ($recipe->RecipeIngredients()->with('Ingredient')->get() as $requirement) {
                $needed = round($requirement->amount, 2);
                $links = StorageIngredient::query()
                    ->select(StorageIngredient::TABLE_NAME . '.*')
                    ->join(Storage::TABLE_NAME, Storage::TABLE_NAME . '.uuid', '=', StorageIngredient::TABLE_NAME . '.storage_uuid')
                    ->where(Storage::TABLE_NAME . '.restaurant_uuid', $restaurant->uuid)
                    ->where(StorageIngredient::TABLE_NAME . '.ingredient_uuid', $requirement->ingredient_uuid)
                    ->orderBy(Storage::TABLE_NAME . '.order')
                    ->lockForUpdate()
                    ->get();

                $pile = PileIngredient::where('restaurant_uuid', $restaurant->uuid)
                    ->where('ingredient_uuid', $requirement->ingredient_uuid)
                    ->lockForUpdate()
                    ->first();

                $stored = round($links->sum('amount') + ($pile->amount ?? 0), 2);
                if ($stored < $needed) {
                    throw new InvalidArgumentException(sprintf(
                        'Not enough %s stored: %s needed, %s stored', $requirement->Ingredient->name, $needed + 0, $stored + 0
                    ));
                }

                foreach ($links as $link) {
                    if ($needed <= 0) {
                        break;
                    }

                    $taken = min($link->amount, $needed);
                    $link->amount = round($link->amount - $taken, 2);
                    $link->amount == 0 ? $link->delete() : $link->save();

                    $needed = round($needed - $taken, 2);
                    $consumed[] = ['storage_uuid' => $link->storage_uuid, 'ingredient_uuid' => $link->ingredient_uuid, 'amount' => $taken];
                }

                // Whatever the storage spaces don't have comes from the pile, which is known to hold enough
                if ($needed > 0) {
                    $pile->amount = round($pile->amount - $needed, 2);
                    $pile->amount == 0 ? $pile->delete() : $pile->save();
                    $consumed[] = ['storage_uuid' => null, 'pile' => true, 'ingredient_uuid' => $requirement->ingredient_uuid, 'amount' => $needed];
                }
            }

            $meal = new Meal();
            $meal->restaurant_uuid = $restaurant->uuid;
            $meal->recipe_uuid = $recipe->uuid;
            $meal->status = Meal::STATUS_PLANNED;
            $meal->portions_left = max(1, $recipe->portions);
            $meal->consumed = $consumed;
            $meal->equipment = $equipment;
            $meal->save();

            return $meal;
        });
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
            $meal->delete();
            return $refund;
        });
    }

    /**
     * Finishes prepping a planned meal, once the prep area has worked through it. The prep equipment becomes dirty,
     * except containers, which hold the prepped meal until it cooks. A recipe without cooking time skips the cooker,
     * and is ready to serve straight away
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

            if ($meal->getRecipe()->cooking_time <= 0) {
                $this->skipCooking($restaurant, $meal, $at ?? $restaurant->getNow());
            }
        });
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
     * Puts a prepped meal in a cooker, which starts its cooking timer. Once cooked, it stays good for the recipe's
     * good for time
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @param Equipment $cooker The cooker equipment
     * @param int $slot Which piece of the cooker equipment to use, from 1
     * @throws InvalidArgumentException Thrown when the meal isn't prepped, or the cooker doesn't exist or is full
     */
    public function cookMeal(Restaurant $restaurant, Meal $meal, Equipment $cooker, int $slot): void
    {
        if ($meal->status !== Meal::STATUS_PREPPED) {
            throw new InvalidArgumentException('Only prepped meals can be cooked');
        }

        if ($cooker->category !== EquipmentCategory::COOKER) {
            throw new InvalidArgumentException($cooker->name . ' is not a cooker');
        }

        if ($slot < 1 || $slot > $this->inventoryService->getTotalStock($cooker)) {
            throw new InvalidArgumentException('There is no ' . $cooker->name . ' ' . $slot);
        }

        DB::transaction(function () use ($restaurant, $meal, $cooker, $slot) {
            $full = Meal::where('cooker_uuid', $cooker->uuid)
                ->where('cooker_slot', $slot)
                ->where('status', Meal::STATUS_COOKING)
                ->lockForUpdate()
                ->exists();

            if ($full) {
                throw new InvalidArgumentException('That ' . $cooker->name . ' is already in use');
            }

            // The cooking equipment goes in use, and prep containers are emptied into the cooker and become dirty
            $this->useEquipment($restaurant, $meal->equipment ?? [], EquipmentStage::COOKING);
            $this->releaseEquipment($restaurant, $this->filterEquipment($meal, EquipmentStage::PREP, container: true), dirty: true);

            $recipe = $meal->getRecipe();
            $endsAt = $restaurant->getNow()->addMinutes($recipe->cooking_time);

            $meal->status = Meal::STATUS_COOKING;
            $meal->cooker_uuid = $cooker->uuid;
            $meal->cooker_slot = $slot;
            $meal->cooking_ends_at = $endsAt;
            $meal->expires_at = $endsAt->copy()->addHours($recipe->good_for);
            $meal->save();
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
        $cooking = $meal->status === Meal::STATUS_COOKING;
        $cooked = $cooking || $meal->status === Meal::STATUS_SERVING;

        return array_values(array_filter($meal->equipment ?? [], fn (array $entry) => match ($entry['stage']) {
            EquipmentStage::PREP->value => $meal->status === Meal::STATUS_PLANNED || ($meal->status === Meal::STATUS_PREPPED && $entry['container']),
            EquipmentStage::COOKING->value => ($cooking && !$meal->cooking_settled) || ($cooked && $entry['container']),
            default => false,
        }));
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
            'status' => $status,
            'prep_time' => $meal->Recipe->prep_time,
            'cooker_uuid' => $meal->cooker_uuid,
            'cooker_slot' => $meal->cooker_slot,
            'cooking_minutes_left' => $status === Meal::STATUS_COOKING ? $this->minutesUntil($now, $meal->cooking_ends_at) : null,
            'cooking_progress' => $status === Meal::STATUS_COOKING ? $this->getCookingProgress($meal, $now) : null,
            'expires_in_minutes' => $meal->expires_at !== null ? $this->minutesUntil($now, $meal->expires_at) : null,
            'spoiled' => $meal->isSpoiledAt($now),
            'portions_left' => $meal->portions_left,
            'quality' => $meal->quality,
            // A finished meal without a quality yet asks for it
            'finished' => $meal->isFinishedAt($now),
        ];
    }

    /**
     * Gets how far along a cooking meal is, from 0 when it went in to 1 when it is done
     */
    private function getCookingProgress(Meal $meal, Carbon $now): float
    {
        $total = $meal->Recipe->cooking_time * 60;
        if ($total <= 0) {
            return 1.0;
        }

        $left = max(0, $meal->cooking_ends_at->getTimestamp() - $now->getTimestamp());
        return round(min(1, max(0, ($total - $left) / $total)), 4);
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
     * Puts consumed ingredients back where they were taken from: the storage spaces, or the pile. When such a space no
     * longer exists, they go into the restaurant's first storage space instead. Capacity is ignored, as they fit before
     * @param Restaurant $restaurant The restaurant
     * @param array $consumed The consumed ingredients, as storage_uuid, ingredient_uuid and amount
     */
    private function refundIngredients(Restaurant $restaurant, array $consumed): void
    {
        $fallback = Storage::where('restaurant_uuid', $restaurant->uuid)->orderBy('order')->first();

        foreach ($consumed as $entry) {
            $ingredient = Ingredient::where('uuid', $entry['ingredient_uuid'])->first();
            if ($ingredient === null) {
                continue;
            }

            if ($entry['pile'] ?? false) {
                $this->inventoryService->addToPile($restaurant, $ingredient, $entry['amount']);
                continue;
            }

            $storage = Storage::where('uuid', $entry['storage_uuid'])->where('restaurant_uuid', $restaurant->uuid)->first() ?? $fallback;
            if ($storage === null) {
                continue;
            }

            $link = StorageIngredient::where('storage_uuid', $storage->uuid)
                ->where('ingredient_uuid', $entry['ingredient_uuid'])
                ->first();

            if ($link === null) {
                $link = new StorageIngredient();
                $link->storage_uuid = $storage->uuid;
                $link->ingredient_uuid = $entry['ingredient_uuid'];
                $link->amount = 0;
            }

            $link->amount = round($link->amount + $entry['amount'], 2);
            $link->save();
        }
    }
}
