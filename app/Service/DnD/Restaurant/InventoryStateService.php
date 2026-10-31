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
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\RecipeMeal;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageEquipment;
use App\Models\DnD\Restaurant\StorageIngredient;

/**
 * Describes the restaurant's inventory as shown on its manage page: the storage spaces and what is in them, the pile,
 * and the ingredient types, equipment types, conversions and recipes it is managed with
 */
class InventoryStateService
{
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
                $items[] = ['type' => InventoryService::TYPE_EQUIPMENT, 'uuid' => $piece->uuid, 'amount' => $amount, 'name' => $piece->name];
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
            'ingredients' => $this->describeIngredients($restaurant, $storedIngredients),
            'equipment' => $this->describeEquipment($restaurant, $storedEquipment),
            'conversions' => $this->describeConversions($restaurant),
            'recipes' => $this->describeRecipes($restaurant),
        ];
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
            ->map(fn (StorageEquipment $link) => ['type' => InventoryService::TYPE_EQUIPMENT, 'uuid' => $link->equipment_uuid, 'amount' => $link->amount]);

        return $ingredients->concat($equipment)->values()->all();
    }

    //| Types

    /**
     * Describes the restaurant's ingredient types, with how much of each is stored in total
     * @param Restaurant $restaurant The restaurant
     * @param array<string, float> $stored How much of each ingredient is stored, keyed by its uuid
     * @return array The ingredient types
     */
    private function describeIngredients(Restaurant $restaurant, array $stored): array
    {
        return Ingredient::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get()
            ->map(fn (Ingredient $ingredient) => [
                'uuid' => $ingredient->uuid,
                'name' => $ingredient->name,
                'category' => $ingredient->category,
                'icon' => $ingredient->icon !== null ? asset('img/' . $ingredient->icon) : null,
                'size' => $ingredient->size,
                'expires_in' => $ingredient->expires_in,
                'quality' => $ingredient->quality,
                'parent_uuid' => $ingredient->parent_uuid,
                'stored' => $stored[$ingredient->uuid] ?? 0,
            ])->values()->all();
    }

    /**
     * Describes the restaurant's equipment types, with how many of each are stored, or owned for equipment that
     * needs no storage
     * @param Restaurant $restaurant The restaurant
     * @param array<string, int> $stored How many of each piece of equipment are stored, keyed by its uuid
     * @return array The equipment types
     */
    private function describeEquipment(Restaurant $restaurant, array $stored): array
    {
        $owned = OwnedEquipment::where('restaurant_uuid', $restaurant->uuid)->pluck('amount', 'equipment_uuid');

        return Equipment::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get()
            ->map(fn (Equipment $piece) => [
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
                    ? ($stored[$piece->uuid] ?? 0)
                    : (int) ($owned[$piece->uuid] ?? 0),
            ])->values()->all();
    }

    /**
     * Describes the restaurant's conversions. Each piece of the input becomes an amount of the output, taking minutes
     * per piece during service
     * @param Restaurant $restaurant The restaurant
     * @return array The conversions, by the names of their input and output
     */
    private function describeConversions(Restaurant $restaurant): array
    {
        return Conversion::where('restaurant_uuid', $restaurant->uuid)->with(['Input', 'Output'])->get()
            ->sortBy(fn (Conversion $conversion) => [$conversion->Input->name, $conversion->Output->name])
            ->map(fn (Conversion $conversion) => [
                'uuid' => $conversion->uuid,
                'input_uuid' => $conversion->input_uuid,
                'output_uuid' => $conversion->output_uuid,
                'output_amount' => $conversion->output_amount,
                'minutes' => $conversion->minutes,
            ])->values()->all();
    }

    /**
     * Describes the restaurant's recipes, with what each takes and makes
     * @param Restaurant $restaurant The restaurant
     * @return array The recipes
     */
    private function describeRecipes(Restaurant $restaurant): array
    {
        return Recipe::where('restaurant_uuid', $restaurant->uuid)
            ->with(['RecipeIngredients', 'RecipeCategories', 'RecipeMeals', 'RecipeEquipment', 'RecipeByproducts'])
            ->orderBy('name')
            ->get()
            ->map(fn (Recipe $recipe) => [
                'uuid' => $recipe->uuid,
                // A variant's stats and lists are the outcome of its base recipe with its changes
                'parent_uuid' => $recipe->parent_uuid,
                'variant_changes' => $recipe->describeVariantChanges(),
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
            ])->values()->all();
    }

    //| Helpers

    /**
     * Describes a stack of an ingredient in the item format of the inventory state
     * @param StorageIngredient|PileIngredient $stack The stack
     * @param Ingredient|null $ingredient The ingredient of the stack, or null when it no longer exists
     * @return array The item
     */
    private function describeStack(StorageIngredient|PileIngredient $stack, ?Ingredient $ingredient): array
    {
        return [
            'type' => InventoryService::TYPE_INGREDIENT,
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
