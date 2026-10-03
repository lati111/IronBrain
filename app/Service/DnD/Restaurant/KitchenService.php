<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
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

        return [
            'clock' => [
                'date' => $now->format('Y-m-d'),
                'time' => $now->format('H:i'),
                'opening_time' => substr($restaurant->opening_time, 0, 5),
                'closing_time' => substr($restaurant->closing_time, 0, 5),
                'shift_total' => $restaurant->shift_total,
            ],
            'recipes' => Recipe::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get()
                ->map(fn (Recipe $recipe) => [
                    'uuid' => $recipe->uuid,
                    'name' => $recipe->name,
                    'price' => $recipe->price,
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
                ])->values()->all(),
        ];
    }

    //| Plating

    /**
     * Plates a portion of a cooked meal, putting the recipe's serving equipment in use. Plating the last portion
     * finishes the meal, which makes its containers dirty
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
     * Plans a meal of a recipe, taking its ingredients out of the restaurant's storage spaces in their order, and
     * putting the equipment used for prepping it in use
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

                $stored = round($links->sum('amount'), 2);
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
     * Prepares a planned meal, which passes the recipe's prep time
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @throws InvalidArgumentException Thrown when the meal isn't planned
     */
    public function prepareMeal(Restaurant $restaurant, Meal $meal): void
    {
        if ($meal->status !== Meal::STATUS_PLANNED) {
            throw new InvalidArgumentException('Only planned meals can be prepared');
        }

        DB::transaction(function () use ($restaurant, $meal) {
            // Prep equipment is dirty once used, except containers, which hold the prepped meal until it cooks
            $this->releaseEquipment($restaurant, $this->filterEquipment($meal, EquipmentStage::PREP, container: false), dirty: true);

            $meal->status = Meal::STATUS_PREPPED;
            $meal->save();

            $this->advanceTime($restaurant, $meal->getRecipe()->prep_time);
        });
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
     * Passes time until a cooking meal is done
     * @param Restaurant $restaurant The restaurant
     * @param Meal $meal The meal
     * @throws InvalidArgumentException Thrown when the meal isn't cooking
     */
    public function skipUntilDone(Restaurant $restaurant, Meal $meal): void
    {
        if ($meal->getStatusAt($restaurant->getNow()) !== Meal::STATUS_COOKING) {
            throw new InvalidArgumentException('Only meals that are still cooking can be skipped');
        }

        $restaurant->setNow($meal->cooking_ends_at->copy());
        $restaurant->save();
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

        $freshUntil = $now->copy()->addMinutes($meal->getRecipe()->fresh_for);

        $meal->status = Meal::STATUS_SERVING;
        $meal->cooker_uuid = null;
        $meal->cooker_slot = null;
        $meal->expires_at = $meal->expires_at !== null && $meal->expires_at->lt($freshUntil) ? $meal->expires_at : $freshUntil;
        $meal->save();
    }

    //| Cleaning

    /**
     * Cleans dirty pieces of a piece of equipment, which passes its cleaning time for each piece cleaned
     * @param Restaurant $restaurant The restaurant
     * @param Equipment $equipment The equipment
     * @param int $amount How many pieces to clean
     * @throws InvalidArgumentException Thrown when fewer pieces are dirty
     */
    public function cleanEquipment(Restaurant $restaurant, Equipment $equipment, int $amount): void
    {
        DB::transaction(function () use ($restaurant, $equipment, $amount) {
            $equipment = Equipment::where('uuid', $equipment->uuid)->lockForUpdate()->first();
            if ($amount < 1 || $amount > $equipment->amount_dirty) {
                throw new InvalidArgumentException(sprintf('Only %d %s %s dirty', $equipment->amount_dirty, $equipment->name, $equipment->amount_dirty === 1 ? 'is' : 'are'));
            }

            $equipment->amount_dirty -= $amount;
            $equipment->save();

            $this->advanceTime($restaurant, $equipment->cleaning_time * $amount);
        });
    }

    /**
     * Cleans every dirty piece of equipment in the restaurant, which passes the cleaning time of each piece
     * @param Restaurant $restaurant The restaurant
     * @return int How many pieces were cleaned
     * @throws InvalidArgumentException Thrown when nothing is dirty
     */
    public function cleanAllEquipment(Restaurant $restaurant): int
    {
        return DB::transaction(function () use ($restaurant) {
            $dirty = Equipment::where('restaurant_uuid', $restaurant->uuid)->where('amount_dirty', '>', 0)->lockForUpdate()->get();
            if ($dirty->isEmpty()) {
                throw new InvalidArgumentException('Nothing is dirty');
            }

            $minutes = 0;
            $pieces = 0;
            foreach ($dirty as $equipment) {
                $minutes += $equipment->cleaning_time * $equipment->amount_dirty;
                $pieces += $equipment->amount_dirty;

                $equipment->amount_dirty = 0;
                $equipment->save();
            }

            $this->advanceTime($restaurant, $minutes);
            return $pieces;
        });
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
            'expires_in_minutes' => $meal->expires_at !== null ? $this->minutesUntil($now, $meal->expires_at) : null,
            'spoiled' => $meal->isSpoiledAt($now),
            'portions_left' => $meal->portions_left,
        ];
    }

    /**
     * Gets how many minutes are left until a moment, rounded up and never below zero
     */
    private function minutesUntil(Carbon $now, Carbon $moment): int
    {
        return max(0, (int) ceil(($moment->getTimestamp() - $now->getTimestamp()) / 60));
    }

    /**
     * Puts consumed ingredients back into the storage spaces they were taken from. When such a space no longer
     * exists, they go into the restaurant's first storage space instead. Capacity is ignored, as they fit before
     * @param Restaurant $restaurant The restaurant
     * @param array $consumed The consumed ingredients, as storage_uuid, ingredient_uuid and amount
     */
    private function refundIngredients(Restaurant $restaurant, array $consumed): void
    {
        $fallback = Storage::where('restaurant_uuid', $restaurant->uuid)->orderBy('order')->first();

        foreach ($consumed as $entry) {
            if (Ingredient::where('uuid', $entry['ingredient_uuid'])->exists() === false) {
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
