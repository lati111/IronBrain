<?php

namespace Tests\Traits\DnD;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\OwnedEquipment;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeByproduct;
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use App\Models\DnD\Restaurant\Room;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageEquipment;
use App\Models\DnD\Restaurant\StorageIngredient;
use App\Models\DnD\Restaurant\Table;

trait RestaurantTestHelper
{
    protected function createTestRestaurant(User $owner, string $name = 'Test Restaurant'): Restaurant
    {
        $restaurant = new Restaurant();
        $restaurant->owner_uuid = $owner->uuid;
        $restaurant->name = $name;
        $restaurant->date = '1492-03-01';
        $restaurant->opening_time = '08:00';
        $restaurant->closing_time = '22:00';
        $restaurant->save();
        return $restaurant;
    }

    /** Gives a user access to a restaurant, as a manager with full control unless another role is given */
    protected function grantRestaurantAccess(Restaurant $restaurant, User $user, string $role = RestaurantUser::ROLE_MANAGER): void
    {
        $access = new RestaurantUser();
        $access->restaurant_uuid = $restaurant->uuid;
        $access->user_uuid = $user->uuid;
        $access->role = $role;
        $access->save();
    }

    protected function createTestRoom(Restaurant $restaurant, int $order = 0, string $name = 'Test Room'): Room
    {
        $room = new Room();
        $room->restaurant_uuid = $restaurant->uuid;
        $room->name = $name;
        $room->order = $order;
        $room->save();
        return $room;
    }

    protected function createTestTable(Restaurant $restaurant, ?Room $room = null, int $capacity = 4, string $name = 'Test Table'): Table
    {
        $table = new Table();
        $table->restaurant_uuid = $restaurant->uuid;
        $table->room_uuid = $room?->uuid;
        $table->name = $name;
        $table->capacity = $capacity;
        $table->save();
        return $table;
    }

    protected function createTestCustomer(Restaurant $restaurant, ?Table $table = null, string $name = 'Test Customer', int $seat = 1): Customer
    {
        $customer = new Customer();
        $customer->restaurant_uuid = $restaurant->uuid;
        $customer->table_uuid = $table?->uuid;
        $customer->seat = $table !== null ? $seat : null;
        $customer->name = $name;
        $customer->save();
        return $customer;
    }

    protected function createTestEmployee(Restaurant $restaurant, ?WorkStation $station = null, string $name = 'Test Employee', float $prepSpeed = 1, float $cleaningSpeed = 1): Employee
    {
        $employee = new Employee();
        $employee->restaurant_uuid = $restaurant->uuid;
        $employee->name = $name;
        $employee->prep_speed = $prepSpeed;
        $employee->cleaning_speed = $cleaningSpeed;
        $employee->station = $station;
        $employee->save();
        return $employee;
    }

    /** Has a new employee wait a table, so orders can be taken there */
    protected function assignTestWaiter(Table $table, string $name = 'Waiter'): Employee
    {
        $waiter = $this->createTestEmployee($table->getRestaurant(), null, $name);
        $table->employee_uuid = $waiter->uuid;
        $table->save();
        return $waiter;
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

    protected function addRecipeIngredient(Recipe $recipe, Ingredient $ingredient, float $amount): RecipeIngredient
    {
        $link = new RecipeIngredient();
        $link->recipe_uuid = $recipe->uuid;
        $link->ingredient_uuid = $ingredient->uuid;
        $link->amount = $amount;
        $link->save();
        return $link;
    }

    protected function createTestMeal(Restaurant $restaurant, Recipe $recipe, string $status = Meal::STATUS_PLANNED): Meal
    {
        $meal = new Meal();
        $meal->restaurant_uuid = $restaurant->uuid;
        $meal->recipe_uuid = $recipe->uuid;
        $meal->status = $status;
        $meal->consumed = [];
        $meal->save();
        return $meal;
    }

    /** Creates cooker equipment that doesn't need storage, with a number of pieces owned */
    protected function createTestCooker(Restaurant $restaurant, int $pieces = 1, string $name = 'Stove'): Equipment
    {
        $cooker = $this->createTestEquipment($restaurant, 1, $name, false);
        $cooker->category = EquipmentCategory::COOKER;
        $cooker->save();
        $this->ownEquipment($restaurant, $cooker, $pieces);
        return $cooker;
    }

    protected function addRecipeEquipment(Recipe $recipe, Equipment $equipment, int $amount, EquipmentStage $stage = EquipmentStage::PREP, bool $container = false): RecipeEquipment
    {
        $link = new RecipeEquipment();
        $link->recipe_uuid = $recipe->uuid;
        $link->equipment_uuid = $equipment->uuid;
        $link->amount = $amount;
        $link->stage = $stage;
        $link->container = $container;
        $link->save();
        return $link;
    }

    protected function addRecipeByproduct(Recipe $recipe, Ingredient $ingredient, float $amount): RecipeByproduct
    {
        $link = new RecipeByproduct();
        $link->recipe_uuid = $recipe->uuid;
        $link->ingredient_uuid = $ingredient->uuid;
        $link->amount = $amount;
        $link->save();
        return $link;
    }

    /** Stores a stack of an ingredient, fresh unless the days it has left are given */
    protected function storeIngredient(Storage $storage, Ingredient $ingredient, float $amount, ?float $daysLeft = null): StorageIngredient
    {
        $link = new StorageIngredient();
        $link->storage_uuid = $storage->uuid;
        $link->ingredient_uuid = $ingredient->uuid;
        $link->amount = $amount;
        $link->days_left = $daysLeft ?? $ingredient->expires_in;
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

    /** Gets how much of an ingredient a storage space holds, across all its stacks */
    protected function getStoredIngredientAmount(Storage $storage, Ingredient $ingredient): float
    {
        return (float) StorageIngredient::where('storage_uuid', $storage->uuid)
            ->where('ingredient_uuid', $ingredient->uuid)
            ->sum('amount');
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
