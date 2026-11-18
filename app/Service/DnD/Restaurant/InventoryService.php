<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Conversion;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\OwnedEquipment;
use App\Models\DnD\Restaurant\PileIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageEquipment;
use App\Models\DnD\Restaurant\StorageIngredient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The restaurant's stock: moving ingredients and equipment between the storage spaces, the pile and general storage,
 * converting ingredients, ingredients ageing as days pass, taking them for cooking, and equipment owned outright
 */
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

    //| Moving stock

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

        $this->transferEquipment($item, $amount, $from, $to);
    }

    /**
     * Moves whole pieces of equipment between storage spaces, or in or out of general storage
     * @see transfer
     */
    private function transferEquipment(Equipment $equipment, float $amount, Storage|string|null $from, Storage|string|null $to): void
    {
        if ($from === self::PILE || $to === self::PILE) {
            throw new InvalidArgumentException('Only ingredients go in the pile');
        }

        if ($equipment->requires_storage === false) {
            throw new InvalidArgumentException('This equipment does not require storage');
        }

        if (floor($amount) !== $amount) {
            throw new InvalidArgumentException('Equipment can only be moved in whole pieces');
        }

        // Amounts are stored with two decimals, so compare and store them at that precision to avoid float drift
        $amount = round($amount, 2);

        DB::transaction(function () use ($equipment, $amount, $from, $to) {
            if ($from !== null) {
                $link = StorageEquipment::where('storage_uuid', $from->uuid)->where('equipment_uuid', $equipment->uuid)->lockForUpdate()->first();
                if ($link === null || round($link->amount - $amount, 2) < 0) {
                    throw new InvalidArgumentException('Not enough stored to move');
                }

                $link->amount = round($link->amount - $amount, 2);
                $link->amount == 0 ? $link->delete() : $link->save();
            }

            if ($to !== null) {
                $to = $this->lockWithRoomFor($to, $equipment, $amount);

                $link = StorageEquipment::where('storage_uuid', $to->uuid)->where('equipment_uuid', $equipment->uuid)->first();
                if ($link === null) {
                    $link = new StorageEquipment();
                    $link->storage_uuid = $to->uuid;
                    $link->equipment_uuid = $equipment->uuid;
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

    /**
     * Describes a stack that expired, for the list shown once a day has passed
     * @param StorageIngredient|PileIngredient $stack The stack
     * @param string $location Where it was stored
     * @return array The ingredient's name, the amount, and where it was stored
     */
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

    //| Equipment

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
}
