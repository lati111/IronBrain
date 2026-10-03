<?php

namespace Tests\Unit\Model\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\OwnedEquipment;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageIngredient;
use Tests\Unit\AbstractUnitTester;

class DndRestaurantModelTest extends AbstractUnitTester
{
    private function createRestaurant(User $owner): Restaurant
    {
        $restaurant = new Restaurant();
        $restaurant->owner_uuid = $owner->uuid;
        $restaurant->name = 'The Prancing Pony';
        $restaurant->date = '1492-03-01';
        $restaurant->opening_time = '08:00';
        $restaurant->closing_time = '22:00';
        $restaurant->save();

        return $restaurant;
    }

    public function testRestaurantAccess(): void
    {
        $owner = $this->getAdminUser();
        $guest = User::where('email', 'test@test.nl')->first();
        $restaurant = $this->createRestaurant($owner);

        $this->assertTrue($restaurant->hasAccess($owner));
        $this->assertFalse($restaurant->hasAccess($guest));

        $restaurant->Users()->attach($guest->uuid);

        $this->assertTrue($restaurant->hasAccess($guest));
        $this->assertNotNull($restaurant->Access()->first()->uuid);
        $this->assertSame($owner->uuid, $restaurant->getOwner()->uuid);
    }

    public function testRestaurantCurrency(): void
    {
        $restaurant = $this->createRestaurant($this->getAdminUser())->fresh();

        $this->assertSame(0, $restaurant->bits);
        $this->assertSame(0, $restaurant->gold);

        $restaurant->bits = 5;
        $restaurant->copper = 12;
        $restaurant->silver = 3;
        $restaurant->gold = 1;
        $restaurant->save();

        $restaurant = $restaurant->fresh();
        $this->assertSame(5, $restaurant->bits);
        $this->assertSame(12, $restaurant->copper);
        $this->assertSame(3, $restaurant->silver);
        $this->assertSame(1, $restaurant->gold);
    }

    public function testRecipeIngredientsAndEquipment(): void
    {
        $restaurant = $this->createRestaurant($this->getAdminUser());

        $recipe = new Recipe();
        $recipe->restaurant_uuid = $restaurant->uuid;
        $recipe->name = 'Stew';
        $recipe->price = 2.5;
        $recipe->prep_time = 10;
        $recipe->cooking_time = 60;
        $recipe->fresh_for = 30;
        $recipe->good_for = 8;
        $recipe->expires_in = 3;
        $recipe->save();

        $ingredient = new Ingredient();
        $ingredient->restaurant_uuid = $restaurant->uuid;
        $ingredient->name = 'Potato';
        $ingredient->expires_in = 14;
        $ingredient->save();

        $stove = new Equipment();
        $stove->restaurant_uuid = $restaurant->uuid;
        $stove->name = 'Stove';
        $stove->category = EquipmentCategory::COOKER;
        $stove->cleaning_time = 15;
        $stove->multiplier = 1.0;
        $stove->save();

        $pot = new Equipment();
        $pot->restaurant_uuid = $restaurant->uuid;
        $pot->parent_uuid = $stove->uuid;
        $pot->name = 'Pot';
        $pot->category = EquipmentCategory::COOKWARE;
        $pot->cleaning_time = 5;
        $pot->multiplier = 1.5;
        $pot->save();

        $recipe->Ingredients()->attach($ingredient->uuid, ['amount' => 3]);
        $recipe->Equipment()->attach($pot->uuid, ['amount' => 1]);
        $recipe->Byproducts()->attach($ingredient->uuid, ['amount' => 2]);

        $this->assertSame(3, $recipe->Ingredients()->first()->pivot->amount);
        $this->assertNotNull(RecipeIngredient::where('recipe_uuid', $recipe->uuid)->first()->uuid);
        $this->assertSame($recipe->uuid, $ingredient->Recipes()->first()->uuid);
        $this->assertSame($pot->uuid, $recipe->Equipment()->first()->uuid);
        $this->assertSame(2, $recipe->Byproducts()->first()->pivot->amount);
        $this->assertSame(1, $recipe->fresh()->portions);
        $this->assertSame($stove->uuid, $pot->getParent()->uuid);
        $this->assertSame(1, $stove->Children()->count());
        $this->assertSame(1.5, $pot->fresh()->multiplier);
        $this->assertSame($restaurant->uuid, $recipe->getRestaurant()->uuid);
        $this->assertSame(1, $restaurant->Recipes()->count());
    }

    public function testStorage(): void
    {
        $restaurant = $this->createRestaurant($this->getAdminUser());

        $storage = new Storage();
        $storage->restaurant_uuid = $restaurant->uuid;
        $storage->name = 'Cellar';
        $storage->capacity = 100;
        $storage->expiration_multiplier = 1.25;
        $storage->save();

        $potato = new Ingredient();
        $potato->restaurant_uuid = $restaurant->uuid;
        $potato->name = 'Potato';
        $potato->expires_in = 14;
        $potato->size = 2;
        $potato->save();

        $cheese = new Ingredient();
        $cheese->restaurant_uuid = $restaurant->uuid;
        $cheese->name = 'Cheese Wheel';
        $cheese->icon = 'cheese.png';
        $cheese->expires_in = 60;
        $cheese->size = 5;
        $cheese->save();

        $storage->Ingredients()->attach($potato->uuid, ['amount' => 10]);
        $storage->Ingredients()->attach($cheese->uuid, ['amount' => 3]);

        $this->assertSame(35.0, $storage->getUsedCapacity());
        $this->assertSame(10, $potato->Storages()->first()->pivot->amount);
        $this->assertNotNull(StorageIngredient::where('storage_uuid', $storage->uuid)->first()->uuid);
        $this->assertNull($storage->fresh()->background_image);
        $this->assertSame(1.25, $storage->fresh()->expiration_multiplier);
        $this->assertSame(1, $restaurant->Storages()->count());
    }

    public function testOwnedEquipment(): void
    {
        $restaurant = $this->createRestaurant($this->getAdminUser());

        $oven = new Equipment();
        $oven->restaurant_uuid = $restaurant->uuid;
        $oven->name = 'Oven';
        $oven->category = EquipmentCategory::COOKER;
        $oven->cleaning_time = 20;
        $oven->multiplier = 1.0;
        $oven->save();

        $restaurant->Owns()->attach($oven->uuid, ['amount' => 2]);

        $this->assertSame(2, $restaurant->Owns()->first()->pivot->amount);
        $this->assertSame($oven->uuid, $restaurant->OwnedEquipment()->first()->getEquipment()->uuid);
        $this->assertNotNull(OwnedEquipment::where('restaurant_uuid', $restaurant->uuid)->first()->uuid);
    }
}
