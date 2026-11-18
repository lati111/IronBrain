<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeCategory;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers ingredient categories: ingredients can fall under one, recipes can take any ingredient of one, and which
 * ingredient is used is chosen when the meal is planned
 */
class IngredientCategoryTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Storage $cellar;
    private Ingredient $carrot;
    private Ingredient $turnip;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '08:00';
        $this->restaurant->save();

        $this->cellar = $this->createTestStorage($this->restaurant, 100, 0, 'Cellar');
        $this->carrot = $this->createCategorisedIngredient('Carrot', 'Vegetable');
        $this->turnip = $this->createCategorisedIngredient('Turnip', 'Vegetable');
    }

    private function createCategorisedIngredient(string $name, ?string $category): Ingredient
    {
        $ingredient = $this->createTestIngredient($this->restaurant, 1, $name);
        $ingredient->category = $category;
        $ingredient->save();

        return $ingredient;
    }

    private function callRoute(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route($route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function createStewRecipe(float $amount = 2): Recipe
    {
        $recipe = $this->createTestRecipe($this->restaurant, 'Stew');
        $link = new RecipeCategory();
        $link->recipe_uuid = $recipe->uuid;
        $link->category = 'Vegetable';
        $link->amount = $amount;
        $link->save();

        return $recipe;
    }

    private function planMeal(Recipe $recipe, array $choices = []): TestResponse
    {
        return $this->callRoute('api.dnd.restaurant.service.meal.plan', ['recipe_uuid' => $recipe->uuid, 'choices' => $choices]);
    }

    //| ingredients

    public function test_ingredients_are_created_with_a_category(): void
    {
        $response = $this->callRoute('api.dnd.restaurant.inventory.ingredient.create', [
            'name' => 'Leek', 'category' => '  Vegetable ', 'expires_in' => 5, 'size' => 1,
        ]);

        $response->assertCreated();
        $this->assertEquals('Vegetable', Ingredient::where('uuid', $response->json('data.uuid'))->value('category'));
        $this->assertEquals('Vegetable', collect($response->json('data.inventory.ingredients'))->firstWhere('name', 'Leek')['category']);
    }

    public function test_ingredients_need_no_category(): void
    {
        $response = $this->callRoute('api.dnd.restaurant.inventory.ingredient.create', [
            'name' => 'Salt', 'category' => '', 'expires_in' => 0, 'size' => 1,
        ]);

        $response->assertCreated();
        $this->assertNull(Ingredient::where('uuid', $response->json('data.uuid'))->value('category'));
    }

    public function test_editing_keeps_the_category_unless_given(): void
    {
        $route = 'api.dnd.restaurant.inventory.ingredient.edit';
        $parameters = ['ingredient_uuid' => $this->carrot->uuid];

        $this->callRoute($route, ['name' => 'Big Carrot'], $parameters)->assertOk();
        $this->assertEquals('Vegetable', $this->carrot->fresh()->category);

        $this->callRoute($route, ['category' => 'Root'], $parameters)->assertOk();
        $this->assertEquals('Root', $this->carrot->fresh()->category);

        $this->callRoute($route, ['category' => ''], $parameters)->assertOk();
        $this->assertNull($this->carrot->fresh()->category);
    }

    //| recipes

    public function test_recipes_are_saved_with_categories(): void
    {
        $response = $this->callRoute('api.dnd.restaurant.inventory.recipe.create', [
            'name' => 'Stew', 'price' => 4.5, 'portions' => 4, 'difficulty' => 12, 'prep_time' => 10,
            'cooking_time' => 30, 'fresh_for' => 60, 'good_for' => 8, 'expires_in' => 3,
            'categories' => [['category' => 'Vegetable', 'amount' => 1.5]],
        ]);

        $response->assertCreated();
        $recipe = collect($response->json('data.inventory.recipes'))->firstWhere('name', 'Stew');
        $this->assertEquals([['category' => 'Vegetable', 'amount' => 1.5]], $recipe['categories']);
    }

    public function test_editing_a_recipe_replaces_its_categories_only_when_given(): void
    {
        $recipe = $this->createStewRecipe();
        $route = 'api.dnd.restaurant.inventory.recipe.edit';
        $parameters = ['recipe_uuid' => $recipe->uuid];

        $this->callRoute($route, ['name' => 'Hearty Stew'], $parameters)->assertOk();
        $this->assertEquals(['Vegetable'], RecipeCategory::where('recipe_uuid', $recipe->uuid)->pluck('category')->all());

        $this->callRoute($route, ['categories' => [['category' => 'Meat', 'amount' => 1]]], $parameters)->assertOk();
        $this->assertEquals(['Meat'], RecipeCategory::where('recipe_uuid', $recipe->uuid)->pluck('category')->all());
    }

    public function test_recipe_categories_are_validated(): void
    {
        $recipe = $this->createStewRecipe();
        $route = 'api.dnd.restaurant.inventory.recipe.edit';
        $parameters = ['recipe_uuid' => $recipe->uuid];

        $this->callRoute($route, ['categories' => [['category' => 'Meat', 'amount' => 0]]], $parameters)->assertBadRequest();
        $this->callRoute($route, ['categories' => [['amount' => 1]]], $parameters)->assertBadRequest();
        $this->callRoute($route, ['categories' => [
            ['category' => 'Meat', 'amount' => 1], ['category' => 'Meat', 'amount' => 2],
        ]], $parameters)->assertBadRequest();
    }

    //| planning

    public function test_the_kitchen_lists_the_ingredients_to_choose_from(): void
    {
        $this->createStewRecipe();
        $this->createCategorisedIngredient('Salt', null);
        $this->storeIngredient($this->cellar, $this->carrot, 3);

        $response = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->get(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));

        $category = collect($response->json('data.recipes'))->firstWhere('name', 'Stew')['categories'][0];
        $this->assertEquals('Vegetable', $category['category']);
        $this->assertEquals([
            ['uuid' => $this->carrot->uuid, 'name' => 'Carrot', 'available' => 3, 'quality' => 0],
            ['uuid' => $this->turnip->uuid, 'name' => 'Turnip', 'available' => 0, 'quality' => 0],
        ], $category['options']);
    }

    public function test_the_chosen_ingredient_is_used_and_returned_when_cancelled(): void
    {
        $recipe = $this->createStewRecipe();
        $this->storeIngredient($this->cellar, $this->carrot, 3);
        $this->storeIngredient($this->cellar, $this->turnip, 3);

        $this->planMeal($recipe, ['Vegetable' => $this->turnip->uuid])->assertCreated();

        $this->assertEquals(3, $this->getStoredIngredientAmount($this->cellar, $this->carrot));
        $this->assertEquals(1, $this->getStoredIngredientAmount($this->cellar, $this->turnip));

        $meal = Meal::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->callRoute('api.dnd.restaurant.service.meal.cancel', [], ['meal_uuid' => $meal->uuid])->assertOk();

        $this->assertEquals(3, $this->getStoredIngredientAmount($this->cellar, $this->turnip));
    }

    public function test_an_ingredient_must_be_chosen_for_each_category(): void
    {
        $recipe = $this->createStewRecipe();
        $this->storeIngredient($this->cellar, $this->carrot, 3);

        $response = $this->planMeal($recipe);

        $response->assertBadRequest();
        $this->assertEquals('Choose which Vegetable to use', $response->json('message'));
        $this->assertFalse(Meal::where('restaurant_uuid', $this->restaurant->uuid)->exists());
    }

    public function test_the_chosen_ingredient_must_be_of_the_category(): void
    {
        $recipe = $this->createStewRecipe();
        $salt = $this->createCategorisedIngredient('Salt', null);
        $this->storeIngredient($this->cellar, $salt, 3);

        $this->planMeal($recipe, ['Vegetable' => $salt->uuid])->assertBadRequest();
        $this->assertEquals(3, $this->getStoredIngredientAmount($this->cellar, $salt));
    }

    public function test_the_chosen_ingredient_must_be_in_stock(): void
    {
        $recipe = $this->createStewRecipe();
        $this->storeIngredient($this->cellar, $this->carrot, 1);

        $this->planMeal($recipe, ['Vegetable' => $this->carrot->uuid])->assertBadRequest();
        $this->assertEquals(1, $this->getStoredIngredientAmount($this->cellar, $this->carrot));
    }

    public function test_an_ingredient_of_another_restaurant_is_not_found(): void
    {
        $recipe = $this->createStewRecipe();
        $other = $this->createTestIngredient($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $this->planMeal($recipe, ['Vegetable' => $other->uuid])->assertNotFound();
    }
}
