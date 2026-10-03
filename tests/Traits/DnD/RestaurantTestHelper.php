<?php

namespace Tests\Traits\DnD;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\OwnedEquipment;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeByproduct;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageEquipment;
use App\Models\DnD\Restaurant\StorageIngredient;

trait RestaurantTestHelper
{
    protected function createTestRestaurant(User $owner, string $name = 'Test Restaurant'): Restaurant
    {
        $restaurant = new Restaurant();
        $restaurant->owner_uuid = $owner->uuid;
        $restaurant->name = $name;
        $restaurant->save();
        return $restaurant;
    }

    protected function grantRestaurantAccess(Restaurant $restaurant, User $user): void
    {
        $access = new RestaurantUser();
        $access->restaurant_uuid = $restaurant->uuid;
        $access->user_uuid = $user->uuid;
        $access->save();
    }

    protected function createTestStorage(Restaurant $restaurant, int $capacity = 100, int $order = 0, string $name = 'Test Storage'): Storage
    {
        $storage = new Storage();
        $storage->restaurant_uuid = $restaurant->uuid;
        $storage->name = $name;
        $storage->capacity = $capacity;
        $storage->expiration_multiplier = 1.0;
        $storage->order = $order;
        $storage->save();
        return $storage;
    }

    protected function createTestIngredient(Restaurant $restaurant, int $size = 1, string $name = 'Test Ingredient'): Ingredient
    {
        $ingredient = new Ingredient();
        $ingredient->restaurant_uuid = $restaurant->uuid;
        $ingredient->name = $name;
        $ingredient->expires_in = 7;
        $ingredient->size = $size;
        $ingredient->save();
        return $ingredient;
    }

    protected function createTestEquipment(Restaurant $restaurant, int $size = 1, string $name = 'Test Equipment', bool $requiresStorage = true): Equipment
    {
        $equipment = new Equipment();
        $equipment->restaurant_uuid = $restaurant->uuid;
        $equipment->name = $name;
        $equipment->category = EquipmentCategory::COOKWARE;
        $equipment->cleaning_time = 5;
        $equipment->multiplier = 1.0;
        $equipment->size = $size;
        $equipment->requires_storage = $requiresStorage;
        $equipment->save();
        return $equipment;
    }

    protected function createTestRecipe(Restaurant $restaurant, string $name = 'Test Recipe'): Recipe
    {
        $recipe = new Recipe();
        $recipe->restaurant_uuid = $restaurant->uuid;
        $recipe->name = $name;
        $recipe->price = 5.0;
        $recipe->portions = 1;
        $recipe->difficulty = 0;
        $recipe->prep_time = 10;
        $recipe->cooking_time = 20;
        $recipe->fresh_for = 30;
        $recipe->good_for = 6;
        $recipe->expires_in = 2;
        $recipe->save();
        return $recipe;
    }

    protected function addRecipeIngredient(Recipe $recipe, Ingredient $ingredient, int $amount): RecipeIngredient
    {
        $link = new RecipeIngredient();
        $link->recipe_uuid = $recipe->uuid;
        $link->ingredient_uuid = $ingredient->uuid;
        $link->amount = $amount;
        $link->save();
        return $link;
    }

    protected function addRecipeByproduct(Recipe $recipe, Ingredient $ingredient, int $amount): RecipeByproduct
    {
        $link = new RecipeByproduct();
        $link->recipe_uuid = $recipe->uuid;
        $link->ingredient_uuid = $ingredient->uuid;
        $link->amount = $amount;
        $link->save();
        return $link;
    }

    protected function storeIngredient(Storage $storage, Ingredient $ingredient, int $amount): StorageIngredient
    {
        $link = new StorageIngredient();
        $link->storage_uuid = $storage->uuid;
        $link->ingredient_uuid = $ingredient->uuid;
        $link->amount = $amount;
        $link->save();
        return $link;
    }

    protected function storeEquipment(Storage $storage, Equipment $equipment, int $amount): StorageEquipment
    {
        $link = new StorageEquipment();
        $link->storage_uuid = $storage->uuid;
        $link->equipment_uuid = $equipment->uuid;
        $link->amount = $amount;
        $link->save();
        return $link;
    }

    protected function getStoredIngredientAmount(Storage $storage, Ingredient $ingredient): int
    {
        return (int) StorageIngredient::where('storage_uuid', $storage->uuid)
            ->where('ingredient_uuid', $ingredient->uuid)
            ->value('amount');
    }

    protected function ownEquipment(Restaurant $restaurant, Equipment $equipment, int $amount): OwnedEquipment
    {
        $link = new OwnedEquipment();
        $link->restaurant_uuid = $restaurant->uuid;
        $link->equipment_uuid = $equipment->uuid;
        $link->amount = $amount;
        $link->save();
        return $link;
    }

    protected function getOwnedEquipmentAmount(Restaurant $restaurant, Equipment $equipment): int
    {
        return (int) OwnedEquipment::where('restaurant_uuid', $restaurant->uuid)
            ->where('equipment_uuid', $equipment->uuid)
            ->value('amount');
    }
}
