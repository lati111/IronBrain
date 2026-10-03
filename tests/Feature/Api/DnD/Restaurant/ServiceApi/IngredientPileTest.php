<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\PileIngredient;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers ingredients brought in during service: added to the pile, cooked with, and sorted into storage later
 */
class IngredientPileTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Ingredient $potato;
    private Storage $cellar;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '08:00';
        $this->restaurant->save();

        $this->potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->cellar = $this->createTestStorage($this->restaurant, 100, 0, 'Cellar');
    }

    private function addToPile(float|string $amount, ?Ingredient $ingredient = null): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.ingredient.add', [
                'restaurant_uuid' => $this->restaurant->uuid,
                'ingredient_uuid' => ($ingredient ?? $this->potato)->uuid,
            ]), ['amount' => $amount]);
    }

    private function transfer(array $parameters): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.inventory.transfer', ['restaurant_uuid' => $this->restaurant->uuid]), array_merge([
                'type' => 'ingredient',
                'item_uuid' => $this->potato->uuid,
            ], $parameters));
    }

    private function getPileAmount(): float
    {
        return PileIngredient::where('restaurant_uuid', $this->restaurant->uuid)->where('ingredient_uuid', $this->potato->uuid)->first()->amount ?? 0;
    }

    //| adding

    public function test_ingredients_are_added_to_the_pile(): void
    {
        $this->storeIngredient($this->cellar, $this->potato, 3);

        $this->addToPile(2)->assertOk();
        $response = $this->addToPile(0.5);

        $response->assertOk();
        $this->assertEquals(2.5, $this->getPileAmount());
        $this->assertEquals(3, $this->getStoredIngredientAmount($this->cellar, $this->potato));

        $ingredient = collect($response->json('data.ingredients'))->firstWhere('name', 'Potato');
        $this->assertEquals(3, $ingredient['stored']);
        $this->assertEquals(2.5, $ingredient['pile']);
    }

    public function test_only_positive_amounts_can_be_added(): void
    {
        $this->addToPile(0)->assertBadRequest();
        $this->addToPile('a')->assertBadRequest();
        $this->assertEquals(0, $this->getPileAmount());
    }

    public function test_ingredient_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestIngredient($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $this->addToPile(1, $other)->assertNotFound();
    }

    //| cooking

    public function test_meals_use_the_pile_once_storage_runs_out_and_return_it_when_cancelled(): void
    {
        $recipe = $this->createTestRecipe($this->restaurant, 'Mash');
        $this->addRecipeIngredient($recipe, $this->potato, 3);
        $this->storeIngredient($this->cellar, $this->potato, 2);
        $this->addToPile(2)->assertOk();

        $meal = $this->planMeal($recipe);

        $this->assertEquals(0, $this->getStoredIngredientAmount($this->cellar, $this->potato));
        $this->assertEquals(1, $this->getPileAmount());

        $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.meal.cancel', ['restaurant_uuid' => $this->restaurant->uuid, 'meal_uuid' => $meal->uuid]))
            ->assertOk();

        $this->assertEquals(2, $this->getStoredIngredientAmount($this->cellar, $this->potato));
        $this->assertEquals(2, $this->getPileAmount());
    }

    private function planMeal(Recipe $recipe): Meal
    {
        $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.meal.plan', ['restaurant_uuid' => $this->restaurant->uuid]), ['recipe_uuid' => $recipe->uuid])
            ->assertCreated();

        return Meal::where('restaurant_uuid', $this->restaurant->uuid)->latest()->first();
    }

    //| sorting

    public function test_the_pile_is_listed_in_the_inventory(): void
    {
        $this->addToPile(4)->assertOk();

        $response = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->get(route('api.dnd.restaurant.inventory.get', ['restaurant_uuid' => $this->restaurant->uuid]));

        $this->assertEquals([['type' => 'ingredient', 'uuid' => $this->potato->uuid, 'amount' => 4]], $response->json('data.pile'));
        $this->assertEquals(4, collect($response->json('data.ingredients'))->firstWhere('name', 'Potato')['stored']);
    }

    public function test_piled_ingredients_are_sorted_into_storage(): void
    {
        $this->addToPile(4)->assertOk();

        $this->transfer(['amount' => 3, 'from_storage_uuid' => 'pile', 'to_storage_uuid' => $this->cellar->uuid])->assertOk();

        $this->assertEquals(1, $this->getPileAmount());
        $this->assertEquals(3, $this->getStoredIngredientAmount($this->cellar, $this->potato));
    }

    public function test_piled_ingredients_can_be_thrown_away(): void
    {
        $this->addToPile(1)->assertOk();

        $this->transfer(['amount' => 1, 'from_storage_uuid' => 'pile'])->assertOk();

        $this->assertFalse(PileIngredient::where('restaurant_uuid', $this->restaurant->uuid)->exists());
    }

    public function test_only_what_is_in_the_pile_can_be_moved(): void
    {
        $this->addToPile(1)->assertOk();

        $response = $this->transfer(['amount' => 2, 'from_storage_uuid' => 'pile', 'to_storage_uuid' => $this->cellar->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('Not enough in the pile to move', $response->json('message'));
        $this->assertEquals(1, $this->getPileAmount());
    }

    public function test_equipment_never_goes_in_the_pile(): void
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot');
        $this->storeEquipment($this->cellar, $pot, 1);

        $response = $this->transfer(['type' => 'equipment', 'item_uuid' => $pot->uuid, 'amount' => 1, 'from_storage_uuid' => $this->cellar->uuid, 'to_storage_uuid' => 'pile']);

        $response->assertBadRequest();
        $this->assertEquals('Only ingredients go in the pile', $response->json('message'));
    }
}
