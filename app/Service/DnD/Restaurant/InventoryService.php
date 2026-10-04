<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Conversion;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\OwnedEquipment;
use App\Models\DnD\Restaurant\PileIngredient;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeByproduct;
use App\Models\DnD\Restaurant\RecipeCategory;
use App\Models\DnD\Restaurant\RecipeMeal;
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageEquipment;
use App\Models\DnD\Restaurant\StorageIngredient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /** @var string The type key for ingredients */
    public const string TYPE_INGREDIENT = 'ingredient';
    /** @var string The type key for equipment */
    public const string TYPE_EQUIPMENT = 'equipment';
    /** @var string Stands for the pile of ingredients brought in during service when moving items */
    public const string PILE = 'pile';
    /** @var float How many days ingredients in the pile lose each day, as it has no expiration multiplier */
    private const float PILE_DECAY = 1.0;

    /**
     * Builds the full inventory state of a restaurant: its date, its storage spaces with their contents, the pile of
     * ingredients waiting to be sorted, and the ingredient and equipment types with how many of each are stored in
     * total. Ingredients are listed per stack, with the days the stack has left. Contents are sorted by name, and
     * then by the days left, soonest to expire first
     * @param Restaurant $restaurant The restaurant to build the state for
     * @return array The inventory state
     */
    public function getInventory(Restaurant $restaurant): array
    {
        $storages = Storage::where('restaurant_uuid', $restaurant->uuid)
            ->with(['StorageIngredients.Ingredient', 'Equipment'])
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        $ingredients = Ingredient::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get();
        $equipment = Equipment::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get();
        $ownedEquipment = OwnedEquipment::where('restaurant_uuid', $restaurant->uuid)->pluck('amount', 'equipment_uuid');
        $conversions = Conversion::where('restaurant_uuid', $restaurant->uuid)->with(['Input', 'Output'])->get()
            ->sortBy(fn (Conversion $conversion) => [$conversion->Input->name, $conversion->Output->name])->values();
        $recipes = Recipe::where('restaurant_uuid', $restaurant->uuid)
            ->with(['RecipeIngredients', 'RecipeCategories', 'RecipeMeals', 'RecipeEquipment', 'RecipeByproducts'])
            ->orderBy('name')
            ->get();

        $storedIngredients = [];
        $storedEquipment = [];

        // Ingredients in the pile count as stored, as they can be cooked with until they are sorted
        $pile = PileIngredient::where('restaurant_uuid', $restaurant->uuid)->with('Ingredient')->get();
        foreach ($pile as $link) {
            $storedIngredients[$link->ingredient_uuid] = round(($storedIngredients[$link->ingredient_uuid] ?? 0) + $link->amount, 2);
        }

        $storageData = $storages->map(function (Storage $storage) use (&$storedIngredients, &$storedEquipment) {
            $items = [];
            $used = 0;

            foreach ($storage->StorageIngredients as $stack) {
                // Ingredients can be stored in fractions, such as half a cabbage
                $used += $stack->amount * $stack->Ingredient->size;
                $storedIngredients[$stack->ingredient_uuid] = round(($storedIngredients[$stack->ingredient_uuid] ?? 0) + $stack->amount, 2);
                $items[] = $this->describeStack($stack, $stack->Ingredient);
            }

            foreach ($storage->Equipment as $piece) {
                $amount = (int) $piece->pivot->amount;
                $used += $amount * $piece->size;
                $storedEquipment[$piece->uuid] = ($storedEquipment[$piece->uuid] ?? 0) + $amount;
                $items[] = ['type' => self::TYPE_EQUIPMENT, 'uuid' => $piece->uuid, 'amount' => $amount, 'name' => $piece->name];
            }

            return [
                'uuid' => $storage->uuid,
                'name' => $storage->name,
                'capacity' => $storage->capacity,
                'used_capacity' => round($used, 2),
                'expiration_multiplier' => $storage->expiration_multiplier,
                'background_image' => $storage->background_image !== null ? asset('img/' . $storage->background_image) : null,
                'items' => $this->sortItems($items),
            ];
        });

        return [
            'date' => $restaurant->date->format('Y-m-d'),
            'storages' => $storageData->values()->all(),
            'pile' => $this->sortItems($pile->map(fn (PileIngredient $stack) => $this->describeStack($stack, $stack->Ingredient))->all()),
            'ingredients' => $ingredients->map(fn (Ingredient $ingredient) => [
                'uuid' => $ingredient->uuid,
                'name' => $ingredient->name,
                'category' => $ingredient->category,
                'icon' => $ingredient->icon !== null ? asset('img/' . $ingredient->icon) : null,
                'size' => $ingredient->size,
                'expires_in' => $ingredient->expires_in,
                'stored' => $storedIngredients[$ingredient->uuid] ?? 0,
            ])->values()->all(),
            'equipment' => $equipment->map(fn (Equipment $piece) => [
                'uuid' => $piece->uuid,
                'name' => $piece->name,
                'description' => $piece->description,
                'category' => $piece->category->value,
                'category_label' => $piece->category->label(),
                'size' => $piece->size,
                'cleaning_time' => $piece->cleaning_time,
                'multiplier' => $piece->multiplier,
                'parent_uuid' => $piece->parent_uuid,
                'requires_storage' => $piece->requires_storage,
                'stored' => $piece->requires_storage
                    ? ($storedEquipment[$piece->uuid] ?? 0)
                    : (int) ($ownedEquipment[$piece->uuid] ?? 0),
            ])->values()->all(),
            // Each piece of the input becomes an amount of the output, taking minutes per piece during service
            'conversions' => $conversions->map(fn (Conversion $conversion) => [
                'uuid' => $conversion->uuid,
                'input_uuid' => $conversion->input_uuid,
                'output_uuid' => $conversion->output_uuid,
                'output_amount' => $conversion->output_amount,
                'minutes' => $conversion->minutes,
            ])->values()->all(),
            'recipes' => $recipes->map(fn (Recipe $recipe) => [
                'uuid' => $recipe->uuid,
                'name' => $recipe->name,
                'type' => $recipe->type,
                'category' => $recipe->category,
                'description' => $recipe->description,
                'price' => $recipe->price,
                'portions' => $recipe->portions,
                'difficulty' => $recipe->difficulty,
                'prep_time' => $recipe->prep_time,
                'cooking_time' => $recipe->cooking_time,
                'fresh_for' => $recipe->fresh_for,
                'good_for' => $recipe->good_for,
                'expires_in' => $recipe->expires_in,
                'ingredients' => $recipe->RecipeIngredients
                    ->map(fn (RecipeIngredient $link) => ['uuid' => $link->ingredient_uuid, 'amount' => $link->amount])
                    ->values()->all(),
                'equipment' => $recipe->RecipeEquipment
                    ->map(fn (RecipeEquipment $link) => [
                        'uuid' => $link->equipment_uuid,
                        'amount' => $link->amount,
                        'stage' => $link->stage->value,
                        'container' => $link->container,
                    ])
                    ->values()->all(),
                // Ingredients taken by category, any ingredient of which will do
                'categories' => $recipe->RecipeCategories
                    ->map(fn (RecipeCategory $link) => ['category' => $link->category, 'amount' => $link->amount])
                    ->values()->all(),
                // Other meals taken, of a specific recipe or of any recipe of a category
                'meals' => $recipe->RecipeMeals
                    ->map(fn (RecipeMeal $link) => ['recipe_uuid' => $link->required_recipe_uuid, 'category' => $link->category, 'portions' => $link->portions])
                    ->values()->all(),
                'byproducts' => $recipe->RecipeByproducts
                    ->map(fn (RecipeByproduct $link) => ['uuid' => $link->ingredient_uuid, 'amount' => $link->amount])
                    ->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Moves an amount of an ingredient or equipment between storage spaces. A null storage stands for the
     * unsaved general storage, meaning the item is either added to or taken out of the saved inventory. Ingredients
     * can also be taken from, or put back in, the pile of ingredients brought in during service. Ingredients are
     * moved per stack, which keeps the days it has left
     * @param Ingredient|Equipment $item The item to move
     * @param float $amount How much of the item to move. Ingredients can be moved in fractions, equipment only whole
     * @param Storage|string|null $from The storage to take the item from, PILE for the pile, or null when it comes from general storage
     * @param Storage|string|null $to The storage to put the item in, PILE for the pile, or null when it leaves the saved inventory
     * @param string|null $stackUuid The stack of the ingredient to take from, or null for the one closest to expiring
     * @param float|null $daysLeft The days ingredients from general storage have left, or null when they are fresh
     * @throws InvalidArgumentException Thrown when equipment is split or piled, the source holds too little or the target has too little capacity
     */
    public function transfer(Ingredient|Equipment $item, float $amount, Storage|string|null $from, Storage|string|null $to, ?string $stackUuid = null, ?float $daysLeft = null): void
    {
        if ($item instanceof Ingredient) {
            $this->transferIngredient($item, $amount, $from, $to, $stackUuid, $daysLeft);
            return;
        }

        if ($item instanceof Equipment && ($from === self::PILE || $to === self::PILE)) {
            throw new InvalidArgumentException('Only ingredients go in the pile');
        }

        if ($item instanceof Equipment && $item->requires_storage === false) {
            throw new InvalidArgumentException('This equipment does not require storage');
        }

        if ($item instanceof Equipment && floor($amount) !== $amount) {
            throw new InvalidArgumentException('Equipment can only be moved in whole pieces');
        }

        // Amounts are stored with two decimals, so compare and store them at that precision to avoid float drift
        $amount = round($amount, 2);

        DB::transaction(function () use ($item, $amount, $from, $to) {
            if ($from !== null) {
                $link = StorageEquipment::where('storage_uuid', $from->uuid)->where('equipment_uuid', $item->uuid)->lockForUpdate()->first();
                if ($link === null || round($link->amount - $amount, 2) < 0) {
                    throw new InvalidArgumentException('Not enough stored to move');
                }

                $link->amount = round($link->amount - $amount, 2);
                $link->amount == 0 ? $link->delete() : $link->save();
            }

            if ($to !== null) {
                $to = $this->lockWithRoomFor($to, $item, $amount);

                $link = StorageEquipment::where('storage_uuid', $to->uuid)->where('equipment_uuid', $item->uuid)->first();
                if ($link === null) {
                    $link = new StorageEquipment();
                    $link->storage_uuid = $to->uuid;
                    $link->equipment_uuid = $item->uuid;
                    $link->amount = 0;
                }

                $link->amount = round($link->amount + $amount, 2);
                $link->save();
            }
        });
    }

    /**
     * Moves an amount of an ingredient, from a stack or from general storage, keeping the days it has left
     * @see transfer
     */
    private function transferIngredient(Ingredient $ingredient, float $amount, Storage|string|null $from, Storage|string|null $to, ?string $stackUuid, ?float $daysLeft): void
    {
        // Amounts are stored with two decimals, so compare and store them at that precision to avoid float drift
        $amount = round($amount, 2);

        DB::transaction(function () use ($ingredient, $amount, $from, $to, $stackUuid, $daysLeft) {
            $daysLeft ??= (float) $ingredient->expires_in;

            if ($from !== null) {
                $query = $from === self::PILE
                    ? PileIngredient::where('restaurant_uuid', $ingredient->restaurant_uuid)
                    : StorageIngredient::where('storage_uuid', $from->uuid);
                $query->where('ingredient_uuid', $ingredient->uuid)->lockForUpdate();

                $stack = $stackUuid !== null
                    ? $query->where('uuid', $stackUuid)->first()
                    : $query->orderBy('days_left')->first();

                if ($stack === null || round($stack->amount - $amount, 2) < 0) {
                    throw new InvalidArgumentException($from === self::PILE ? 'Not enough in the pile to move' : 'Not enough stored to move');
                }

                $daysLeft = $stack->days_left;
                $this->takeFromStack($stack, $amount);
            }

            if ($to === self::PILE) {
                $this->addToPile($ingredient->getRestaurant(), $ingredient, $amount, $daysLeft);
            } elseif ($to !== null) {
                $this->addToStorage($this->lockWithRoomFor($to, $ingredient, $amount), $ingredient, $amount, $daysLeft);
            }
        });
    }

    /**
     * Locks a storage space, checking it has room for an amount of an item
     * @throws InvalidArgumentException Thrown when the storage space has too little capacity left
     */
    private function lockWithRoomFor(Storage $storage, Ingredient|Equipment $item, float $amount): Storage
    {
        $storage = Storage::where('uuid', $storage->uuid)->lockForUpdate()->first();
        // Rounded, as fractional sizes would otherwise fail exact fits through float imprecision
        if (round($storage->getUsedCapacity() + ($item->size * $amount), 2) > $storage->capacity) {
            throw new InvalidArgumentException('Not enough capacity in ' . $storage->name);
        }

        return $storage;
    }

    /**
     * Adds an amount of an ingredient to a storage space, on the stack with the same days left, or a new one
     * @param float $daysLeft The days the ingredient has left
     */
    private function addToStorage(Storage $storage, Ingredient $ingredient, float $amount, float $daysLeft): void
    {
        $stack = StorageIngredient::where('storage_uuid', $storage->uuid)->where('ingredient_uuid', $ingredient->uuid)
            ->where('days_left', round($daysLeft, 2))->lockForUpdate()->first();

        if ($stack === null) {
            $stack = new StorageIngredient();
            $stack->storage_uuid = $storage->uuid;
            $stack->ingredient_uuid = $ingredient->uuid;
            $stack->days_left = round($daysLeft, 2);
            $stack->amount = 0;
        }

        $stack->amount = round($stack->amount + $amount, 2);
        $stack->save();
    }

    /**
     * Takes an amount off a stack, removing it once it is empty
     */
    private function takeFromStack(StorageIngredient|PileIngredient $stack, float $amount): void
    {
        $stack->amount = round($stack->amount - $amount, 2);
        $stack->amount == 0 ? $stack->delete() : $stack->save();
    }

    //| Conversions

    /**
     * Converts an amount of a stored ingredient straight away, from one of its stacks. What it becomes is fresh, and
     * goes where the input was: in the same storage space, which needs room for it, or in the pile
     * @param Conversion $conversion The conversion
     * @param float $amount How much of the input to convert
     * @param Storage|string $from The storage space the input is in, or PILE for the pile
     * @param string|null $stackUuid The stack of the input to take from, or null for the one closest to expiring
     * @throws InvalidArgumentException Thrown when too little of the input is there, it would make nothing, or the
     *                                  storage space has too little room left for what it becomes
     */
    public function convert(Conversion $conversion, float $amount, Storage|string $from, ?string $stackUuid = null): void
    {
        $output = $conversion->getOutputFor($amount);
        if ($output <= 0) {
            throw new InvalidArgumentException('Too little to convert');
        }

        DB::transaction(function () use ($conversion, $amount, $from, $stackUuid, $output) {
            $this->transfer($conversion->Input, $amount, $from, null, $stackUuid);
            $this->transfer($conversion->Output, $output, null, $from);
        });
    }

    //| Pile

    /**
     * Adds an ingredient to the restaurant's pile, where ingredients brought in during service wait to be sorted
     * into the storage spaces. The pile has no capacity
     * @param Restaurant $restaurant The restaurant
     * @param Ingredient $ingredient The ingredient
     * @param float $amount How much to add
     * @param float|null $daysLeft The days the ingredient has left, or null when it is fresh
     */
    public function addToPile(Restaurant $restaurant, Ingredient $ingredient, float $amount, ?float $daysLeft = null): void
    {
        $daysLeft = round($daysLeft ?? $ingredient->expires_in, 2);

        DB::transaction(function () use ($restaurant, $ingredient, $amount, $daysLeft) {
            $link = PileIngredient::where('restaurant_uuid', $restaurant->uuid)
                ->where('ingredient_uuid', $ingredient->uuid)
                ->where('days_left', $daysLeft)
                ->lockForUpdate()
                ->first();

            if ($link === null) {
                $link = new PileIngredient();
                $link->restaurant_uuid = $restaurant->uuid;
                $link->ingredient_uuid = $ingredient->uuid;
                $link->days_left = $daysLeft;
                $link->amount = 0;
            }

            $link->amount = round($link->amount + $amount, 2);
            $link->save();
        });
    }

    //| Expiry

    /**
     * Moves the restaurant's date a day forward. Stored ingredients lose a day, multiplied by the expiration
     * multiplier of their storage space, and those in the pile a whole day. Stacks that run out of days have expired
     * and are thrown away. Ingredients that expire in 0 days never expire
     * @param Restaurant $restaurant The restaurant
     * @return array The stacks that expired, each with the ingredient's name, the amount, and where it was stored
     */
    public function advanceDay(Restaurant $restaurant): array
    {
        return DB::transaction(function () use ($restaurant) {
            $restaurant->date = $restaurant->date->copy()->addDay()->format('Y-m-d');
            $restaurant->save();

            $expired = [];
            $storages = Storage::where('restaurant_uuid', $restaurant->uuid)->with('StorageIngredients.Ingredient')
                ->orderBy('order')->get();
            foreach ($storages as $storage) {
                foreach ($storage->StorageIngredients as $stack) {
                    if ($this->decay($stack, $storage->expiration_multiplier)) {
                        $expired[] = $this->describeExpired($stack, $storage->name);
                    }
                }
            }

            foreach (PileIngredient::where('restaurant_uuid', $restaurant->uuid)->with('Ingredient')->get() as $stack) {
                if ($this->decay($stack, self::PILE_DECAY)) {
                    $expired[] = $this->describeExpired($stack, 'Unsorted');
                }
            }

            return $expired;
        });
    }

    /**
     * Takes a day off a stack at a rate, throwing it away once no days are left
     * @return bool Whether it expired
     */
    private function decay(StorageIngredient|PileIngredient $stack, float $rate): bool
    {
        if (!$stack->Ingredient?->expires()) {
            return false;
        }

        $stack->days_left = round($stack->days_left - $rate, 2);
        if ($stack->days_left <= 0) {
            $stack->delete();
            return true;
        }

        $stack->save();
        return false;
    }

    private function describeExpired(StorageIngredient|PileIngredient $stack, string $location): array
    {
        return ['name' => $stack->Ingredient?->name ?? 'Unknown', 'amount' => $stack->amount, 'location' => $location];
    }

    //| Cooking

    /**
     * Takes an amount of an ingredient for cooking, from the stacks closest to expiring first, across the storage
     * spaces and the pile
     * @param Restaurant $restaurant The restaurant
     * @param Ingredient $ingredient The ingredient
     * @param float $needed How much is needed
     * @return array What was taken from each stack, as storage_uuid or pile, ingredient_uuid, amount and days_left,
     *               so it can be put back
     * @throws InvalidArgumentException Thrown when too little of the ingredient is stored
     */
    public function takeForCooking(Restaurant $restaurant, Ingredient $ingredient, float $needed): array
    {
        $needed = round($needed, 2);
        $stacks = StorageIngredient::query()
            ->select(StorageIngredient::TABLE_NAME . '.*', Storage::TABLE_NAME . '.order as storage_order')
            ->join(Storage::TABLE_NAME, Storage::TABLE_NAME . '.uuid', '=', StorageIngredient::TABLE_NAME . '.storage_uuid')
            ->where(Storage::TABLE_NAME . '.restaurant_uuid', $restaurant->uuid)
            ->where(StorageIngredient::TABLE_NAME . '.ingredient_uuid', $ingredient->uuid)
            ->lockForUpdate()
            ->get()
            ->concat(PileIngredient::where('restaurant_uuid', $restaurant->uuid)->where('ingredient_uuid', $ingredient->uuid)
                ->lockForUpdate()->get())
            // Closest to expiring first, and the storage spaces in their order before the pile
            ->sortBy([['days_left', 'asc'], fn ($a, $b) => ($a->storage_order ?? PHP_INT_MAX) <=> ($b->storage_order ?? PHP_INT_MAX)])
            ->values();

        $stored = round($stacks->sum('amount'), 2);
        if ($stored < $needed) {
            throw new InvalidArgumentException(sprintf('Not enough %s stored: %s needed, %s stored', $ingredient->name, $needed + 0, $stored + 0));
        }

        $taken = [];
        foreach ($stacks as $stack) {
            if ($needed <= 0) {
                break;
            }

            $amount = min($stack->amount, $needed);
            $needed = round($needed - $amount, 2);
            $taken[] = [
                'storage_uuid' => $stack instanceof StorageIngredient ? $stack->storage_uuid : null,
                'pile' => $stack instanceof PileIngredient,
                'ingredient_uuid' => $ingredient->uuid,
                'amount' => $amount,
                'days_left' => $stack->days_left,
            ];

            unset($stack->storage_order);
            $this->takeFromStack($stack, $amount);
        }

        return $taken;
    }

    /**
     * Puts ingredients taken for cooking back where they came from, with the days they had left. When their storage
     * space no longer exists, they go into the first one instead. Capacity is ignored, as they fit before
     * @param Restaurant $restaurant The restaurant
     * @param array $taken What was taken, as given by takeForCooking
     */
    public function putBack(Restaurant $restaurant, array $taken): void
    {
        $fallback = Storage::where('restaurant_uuid', $restaurant->uuid)->orderBy('order')->first();

        foreach ($taken as $entry) {
            $ingredient = Ingredient::where('uuid', $entry['ingredient_uuid'])->first();
            if ($ingredient === null) {
                continue;
            }

            // Ingredients taken before stacks had days left come back fresh
            $daysLeft = (float) ($entry['days_left'] ?? $ingredient->expires_in);

            if ($entry['pile'] ?? false) {
                $this->addToPile($restaurant, $ingredient, $entry['amount'], $daysLeft);
                continue;
            }

            $storage = Storage::where('uuid', $entry['storage_uuid'])->where('restaurant_uuid', $restaurant->uuid)->first() ?? $fallback;
            if ($storage !== null) {
                $this->addToStorage($storage, $ingredient, $entry['amount'], $daysLeft);
            }
        }
    }

    /**
     * Adjusts how much of an equipment type a restaurant directly owns. Only applies to equipment that does not
     * require storage, as it is never placed in a storage space
     * @param Restaurant $restaurant The restaurant that owns the equipment
     * @param Equipment $equipment The equipment, which must not require storage
     * @param int $delta How much to add (positive) or remove (negative)
     * @throws InvalidArgumentException Thrown when the equipment requires storage or too little is owned to remove
     */
    public function adjustOwnedEquipment(Restaurant $restaurant, Equipment $equipment, int $delta): void
    {
        if ($equipment->requires_storage) {
            throw new InvalidArgumentException('This equipment must be stored in a storage space');
        }

        DB::transaction(function () use ($restaurant, $equipment, $delta) {
            $link = OwnedEquipment::where('restaurant_uuid', $restaurant->uuid)
                ->where('equipment_uuid', $equipment->uuid)
                ->lockForUpdate()
                ->first();

            $amount = ($link->amount ?? 0) + $delta;
            if ($amount < 0) {
                throw new InvalidArgumentException('Not enough owned to remove');
            }

            if ($link === null) {
                if ($amount === 0) {
                    return;
                }

                $link = new OwnedEquipment();
                $link->restaurant_uuid = $restaurant->uuid;
                $link->equipment_uuid = $equipment->uuid;
            }

            $link->amount = $amount;
            $amount === 0 ? $link->delete() : $link->save();
        });
    }

    /**
     * Gets how much of an equipment type is stored and owned in total across a restaurant, regardless of whether
     * it requires storage
     * @param Equipment $equipment The equipment
     * @return int The total stored and owned amount
     */
    public function getTotalStock(Equipment $equipment): int
    {
        $stored = (int) StorageEquipment::where('equipment_uuid', $equipment->uuid)->sum('amount');
        $owned = (int) OwnedEquipment::where('equipment_uuid', $equipment->uuid)->sum('amount');
        return $stored + $owned;
    }

    /**
     * Gets the contents of a storage space in the same item format as the inventory state
     * @param Storage $storage The storage space
     * @return array The items stored in the storage space
     */
    public function getContents(Storage $storage): array
    {
        $ingredients = StorageIngredient::where('storage_uuid', $storage->uuid)->with('Ingredient')->get()
            ->map(fn (StorageIngredient $stack) => $this->describeStack($stack, $stack->Ingredient));

        $equipment = StorageEquipment::where('storage_uuid', $storage->uuid)->get()
            ->map(fn (StorageEquipment $link) => ['type' => self::TYPE_EQUIPMENT, 'uuid' => $link->equipment_uuid, 'amount' => $link->amount]);

        return $ingredients->concat($equipment)->values()->all();
    }

    /**
     * Describes a stack of an ingredient in the item format of the inventory state
     */
    private function describeStack(StorageIngredient|PileIngredient $stack, ?Ingredient $ingredient): array
    {
        return [
            'type' => self::TYPE_INGREDIENT,
            'uuid' => $stack->ingredient_uuid,
            'stack_uuid' => $stack->uuid,
            'amount' => $stack->amount,
            // Null for ingredients that never expire
            'days_left' => $ingredient?->expires() ? $stack->days_left : null,
            'name' => $ingredient?->name ?? '',
        ];
    }

    /**
     * Sorts items by name, and stacks of the same ingredient by the days they have left, soonest to expire first
     * @param array $items The items, each with a name, and days left for ingredients
     * @return array The sorted items
     */
    private function sortItems(array $items): array
    {
        usort($items, fn (array $a, array $b) => [strtolower($a['name']), $a['days_left'] ?? 0] <=> [strtolower($b['name']), $b['days_left'] ?? 0]);
        return array_values($items);
    }
}
