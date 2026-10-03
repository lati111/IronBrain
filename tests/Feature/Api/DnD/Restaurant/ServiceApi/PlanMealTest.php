<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageIngredient;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class PlanMealTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;
    private Ingredient $potato;
    private Storage $cellar;
    private Storage $pantry;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->recipe = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->addRecipeIngredient($this->recipe, $this->potato, 2.5);

        $this->cellar = $this->createTestStorage($this->restaurant, 100, 0, 'Cellar');
        $this->pantry = $this->createTestStorage($this->restaurant, 100, 1, 'Pantry');
        $this->storeIngredient($this->cellar, $this->potato, 1.5);
        $this->storeIngredient($this->pantry, $this->potato, 4);
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.service.meal.plan', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Meal
    {
        return new Meal();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge(['recipe_uuid' => $this->recipe->uuid], $overwrites);
    }

    private function plan(array $overwrites = [])
    {
        return $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $this->getPostParameters($overwrites));
    }

    public function test_recipe_validation(): void
    {
        $this->assertRequiredValidation('recipe_uuid', 'recipe uuid');
    }

    public function test_planning_takes_ingredients_from_storage_in_order(): void
    {
        $response = $this->plan();

        $response->assertCreated();
        $this->assertEquals('planned', $response->json('data.meals.0.status'));
        // The cellar comes first and is emptied, the rest comes from the pantry
        $this->assertFalse(StorageIngredient::where('storage_uuid', $this->cellar->uuid)->exists());
        $this->assertEquals(3.0, $this->getStoredIngredientAmount($this->pantry, $this->potato));
    }

    public function test_too_few_ingredients_plans_nothing(): void
    {
        $this->addRecipeIngredient($this->recipe, $this->createTestIngredient($this->restaurant, 1, 'Truffle'), 1);

        $response = $this->plan();

        $response->assertBadRequest();
        $this->assertApiMessage('Not enough Truffle stored: 1 needed, 0 stored', $response);
        $this->assertFalse(Meal::where('restaurant_uuid', $this->restaurant->uuid)->exists());
        $this->assertEquals(1.5, $this->getStoredIngredientAmount($this->cellar, $this->potato));
    }

    public function test_recipe_of_another_restaurant_is_not_found(): void
    {
        $otherRecipe = $this->createTestRecipe($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->plan(['recipe_uuid' => $otherRecipe->uuid]);

        $response->assertNotFound();
        $this->assertApiMessage('Recipe not found', $response);
    }
}
