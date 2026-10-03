<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\OwnedEquipment;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeByproduct;
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

    /**
     * Builds the full inventory state of a restaurant: its storage spaces with their contents, and the
     * ingredient and equipment types with how many of each are stored in total
     * @param Restaurant $restaurant The restaurant to build the state for
     * @return array The inventory state
     */
    public function getInventory(Restaurant $restaurant): array
    {
        $storages = Storage::where('restaurant_uuid', $restaurant->uuid)
            ->with(['Ingredients', 'Equipment'])
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        $ingredients = Ingredient::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get();
        $equipment = Equipment::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get();
        $ownedEquipment = OwnedEquipment::where('restaurant_uuid', $restaurant->uuid)->pluck('amount', 'equipment_uuid');
        $recipes = Recipe::where('restaurant_uuid', $restaurant->uuid)
            ->with(['RecipeIngredients', 'RecipeEquipment', 'RecipeByproducts'])
            ->orderBy('name')
            ->get();

        $storedIngredients = [];
        $storedEquipment = [];

        $storageData = $storages->map(function (Storage $storage) use (&$storedIngredients, &$storedEquipment) {
            $items = [];
            $used = 0;

            foreach ($storage->Ingredients as $ingredient) {
                // Ingredients can be stored in fractions, such as half a cabbage
                $amount = (float) $ingredient->pivot->amount;
                $used += $amount * $ingredient->size;
                $storedIngredients[$ingredient->uuid] = round(($storedIngredients[$ingredient->uuid] ?? 0) + $amount, 2);
                $items[] = ['type' => self::TYPE_INGREDIENT, 'uuid' => $ingredient->uuid, 'amount' => $amount];
            }

            foreach ($storage->Equipment as $piece) {
                $amount = (int) $piece->pivot->amount;
                $used += $amount * $piece->size;
                $storedEquipment[$piece->uuid] = ($storedEquipment[$piece->uuid] ?? 0) + $amount;
                $items[] = ['type' => self::TYPE_EQUIPMENT, 'uuid' => $piece->uuid, 'amount' => $amount];
            }

            return [
                'uuid' => $storage->uuid,
                'name' => $storage->name,
                'capacity' => $storage->capacity,
                'used_capacity' => round($used, 2),
                'expiration_multiplier' => $storage->expiration_multiplier,
                'background_image' => $storage->background_image !== null ? asset('img/' . $storage->background_image) : null,
                'items' => $items,
            ];
        });

        return [
            'storages' => $storageData->values()->all(),
            'ingredients' => $ingredients->map(fn (Ingredient $ingredient) => [
                'uuid' => $ingredient->uuid,
                'name' => $ingredient->name,
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
            'recipes' => $recipes->map(fn (Recipe $recipe) => [
                'uuid' => $recipe->uuid,
                'name' => $recipe->name,
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
                'byproducts' => $recipe->RecipeByproducts
                    ->map(fn (RecipeByproduct $link) => ['uuid' => $link->ingredient_uuid, 'amount' => $link->amount])
                    ->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Moves an amount of an ingredient or equipment between storage spaces. A null storage stands for the
     * unsaved general storage, meaning the item is either added to or taken out of the saved inventory
     * @param Ingredient|Equipment $item The item to move
     * @param float $amount How much of the item to move. Ingredients can be moved in fractions, equipment only whole
     * @param Storage|null $from The storage to take the item from, or null when it comes from general storage
     * @param Storage|null $to The storage to put the item in, or null when it leaves the saved inventory
     * @throws InvalidArgumentException Thrown when equipment is split, the source holds too little or the target has too little capacity
     */
    public function transfer(Ingredient|Equipment $item, float $amount, ?Storage $from, ?Storage $to): void
    {
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
                $link = $this->getLinkQuery($item, $from)->lockForUpdate()->first();
                if ($link === null || round($link->amount - $amount, 2) < 0) {
                    throw new InvalidArgumentException('Not enough stored to move');
                }

                $link->amount = round($link->amount - $amount, 2);
                $link->amount == 0 ? $link->delete() : $link->save();
            }

            if ($to !== null) {
                $to = Storage::where('uuid', $to->uuid)->lockForUpdate()->first();
                // Rounded, as fractional ingredient sizes would otherwise fail exact fits through float imprecision
                if (round($to->getUsedCapacity() + ($item->size * $amount), 2) > $to->capacity) {
                    throw new InvalidArgumentException('Not enough capacity in ' . $to->name);
                }

                $link = $this->getLinkQuery($item, $to)->first();
                if ($link === null) {
                    $link = $item instanceof Ingredient ? new StorageIngredient() : new StorageEquipment();
                    $link->storage_uuid = $to->uuid;
                    $link->{$item instanceof Ingredient ? 'ingredient_uuid' : 'equipment_uuid'} = $item->uuid;
                    $link->amount = 0;
                }

                $link->amount = round($link->amount + $amount, 2);
                $link->save();
            }
        });
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
        $ingredients = StorageIngredient::where('storage_uuid', $storage->uuid)->get()
            ->map(fn (StorageIngredient $link) => ['type' => self::TYPE_INGREDIENT, 'uuid' => $link->ingredient_uuid, 'amount' => $link->amount]);

        $equipment = StorageEquipment::where('storage_uuid', $storage->uuid)->get()
            ->map(fn (StorageEquipment $link) => ['type' => self::TYPE_EQUIPMENT, 'uuid' => $link->equipment_uuid, 'amount' => $link->amount]);

        return $ingredients->concat($equipment)->values()->all();
    }

    /**
     * Gets the query for the link between an item and a storage space
     * @param Ingredient|Equipment $item The item
     * @param Storage $storage The storage space
     */
    private function getLinkQuery(Ingredient|Equipment $item, Storage $storage)
    {
        if ($item instanceof Ingredient) {
            return StorageIngredient::where('storage_uuid', $storage->uuid)->where('ingredient_uuid', $item->uuid);
        }

        return StorageEquipment::where('storage_uuid', $storage->uuid)->where('equipment_uuid', $item->uuid);
    }
}
