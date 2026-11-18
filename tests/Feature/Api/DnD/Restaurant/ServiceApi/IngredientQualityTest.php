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
 * Covers the quality of ingredients, whose average over the ingredients a meal is made with is added to the quality
 * it is served at, and variants of ingredients that can be used instead of the one a recipe takes
 */
class IngredientQualityTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;
    private Storage $storage;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->recipe = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->storage = $this->createTestStorage($this->restaurant);
    }

    /** Creates a stored ingredient of a quality, optionally as a variant of another */
    private function createStoredIngredient(string $name, int $quality, float $stored = 10, ?Ingredient $parent = null, ?string $category = null): Ingredient
    {
        $ingredient = $this->createTestIngredient($this->restaurant, 1, $name);
        $ingredient->quality = $quality;
        $ingredient->parent_uuid = $parent?->uuid;
        $ingredient->category = $category;
        $ingredient->save();
        if ($stored > 0) {
            $this->storeIngredient($this->storage, $ingredient, $stored);
        }
        return $ingredient;
    }

    private function callApi(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function plan(array $parameters = []): TestResponse
    {
        return $this->callApi('service.meal.plan', array_merge(['recipe_uuid' => $this->recipe->uuid], $parameters));
    }

    private function latestMeal(): ?Meal
    {
        return Meal::where('restaurant_uuid', $this->restaurant->uuid)->latest()->first();
    }

    private function getState(): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->get(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));
    }

    //| quality

    public function test_meals_take_the_average_quality_of_each_ingredient_used(): void
    {
        // Each ingredient counts once, however much of it is used
        $this->addRecipeIngredient($this->recipe, $this->createStoredIngredient('Beef', 4), 3);
        $this->addRecipeIngredient($this->recipe, $this->createStoredIngredient('Salt', 2), 1);
        $herb = $this->createStoredIngredient('Thyme', 9, category: 'Herb');
        $link = new RecipeCategory();
        $link->recipe_uuid = $this->recipe->uuid;
        $link->category = 'Herb';
        $link->amount = 1;
        $link->save();

        $this->plan(['choices' => ['Herb' => $herb->uuid]])->assertCreated();

        $this->assertEquals(5, $this->latestMeal()->ingredient_quality);
    }

    public function test_the_average_is_rounded(): void
    {
        $this->addRecipeIngredient($this->recipe, $this->createStoredIngredient('Beef', 4), 1);
        $this->addRecipeIngredient($this->recipe, $this->createStoredIngredient('Salt', 1), 1);

        $this->plan()->assertCreated();

        $this->assertEquals(3, $this->latestMeal()->ingredient_quality);
    }

    public function test_a_recipe_without_ingredients_adds_nothing(): void
    {
        $this->plan()->assertCreated();

        $this->assertEquals(0, $this->latestMeal()->ingredient_quality);
    }

    public function test_the_ingredient_quality_is_added_to_the_served_quality(): void
    {
        $this->addRecipeIngredient($this->recipe, $this->createStoredIngredient('Beef', 4), 1);
        $this->plan()->assertCreated();
        $meal = $this->latestMeal();
        $meal->quality = 10;
        $meal->quality_bonus = 2;
        $meal->save();

        $described = collect($this->getState()->json('data.meals'))->firstWhere('uuid', $meal->uuid);

        $this->assertEquals(16, $meal->getServedQuality());
        $this->assertEquals(16, $described['quality']);
        $this->assertEquals(4, $described['ingredient_quality']);
    }

    //| variants

    public function test_the_state_lists_variants_of_ingredients(): void
    {
        $beef = $this->createStoredIngredient('Beef', 1, 2);
        $wagyu = $this->createStoredIngredient('Wagyu', 8, 5, $beef);
        $this->addRecipeIngredient($this->recipe, $beef, 3);

        $entry = collect($this->getState()->json('data.recipes'))->firstWhere('name', 'Stew')['ingredients'][0];

        $this->assertEquals($beef->uuid, $entry['ingredient_uuid']);
        $this->assertEquals(3, $entry['amount']);
        $this->assertEquals([
            ['uuid' => $beef->uuid, 'name' => 'Beef', 'available' => 2, 'quality' => 1],
            ['uuid' => $wagyu->uuid, 'name' => 'Wagyu', 'available' => 5, 'quality' => 8],
        ], $entry['options']);
    }

    public function test_planning_can_use_a_variant_of_an_ingredient(): void
    {
        $beef = $this->createStoredIngredient('Beef', 1, 0);
        $wagyu = $this->createStoredIngredient('Wagyu', 8, 5, $beef);
        $this->addRecipeIngredient($this->recipe, $beef, 3);

        $this->plan(['ingredients' => [$beef->uuid => $wagyu->uuid]])->assertCreated();

        $meal = $this->latestMeal();
        $this->assertEquals(2, $this->getStoredIngredientAmount($this->storage, $wagyu));
        $this->assertEquals($wagyu->uuid, $meal->consumed[0]['ingredient_uuid']);
        $this->assertEquals(8, $meal->ingredient_quality);
    }

    public function test_planning_uses_the_ingredient_itself_by_default(): void
    {
        $beef = $this->createStoredIngredient('Beef', 1, 5);
        $wagyu = $this->createStoredIngredient('Wagyu', 8, 5, $beef);
        $this->addRecipeIngredient($this->recipe, $beef, 3);

        $this->plan()->assertCreated();

        $this->assertEquals(2, $this->getStoredIngredientAmount($this->storage, $beef));
        $this->assertEquals(5, $this->getStoredIngredientAmount($this->storage, $wagyu));
    }

    public function test_only_variants_can_be_used_instead(): void
    {
        $beef = $this->createStoredIngredient('Beef', 1, 5);
        $salt = $this->createStoredIngredient('Salt', 3, 5);
        $this->addRecipeIngredient($this->recipe, $beef, 3);

        $response = $this->plan(['ingredients' => [$beef->uuid => $salt->uuid]]);

        $response->assertBadRequest();
        $this->assertEquals('Salt cannot be used instead of Beef', $response->json('message'));
        $this->assertNull($this->latestMeal());
        $this->assertEquals(5, $this->getStoredIngredientAmount($this->storage, $beef));
    }

    //| managing

    public function test_ingredients_are_created_and_edited_with_a_quality_and_parent(): void
    {
        $beef = $this->createStoredIngredient('Beef', 0, 0);

        $response = $this->callApi('inventory.ingredient.create', ['name' => 'Wagyu', 'expires_in' => 3, 'size' => 1, 'quality' => 7, 'parent_uuid' => $beef->uuid]);
        $response->assertCreated();
        $wagyu = Ingredient::find($response->json('data.uuid'));
        $this->assertEquals(7, $wagyu->quality);
        $this->assertEquals($beef->uuid, $wagyu->parent_uuid);

        $this->callApi('inventory.ingredient.edit', ['quality' => -2, 'parent_uuid' => null], ['ingredient_uuid' => $wagyu->uuid])->assertOk();
        $wagyu->refresh();
        $this->assertEquals(-2, $wagyu->quality);
        $this->assertNull($wagyu->parent_uuid);
    }

    public function test_an_ingredient_cannot_be_a_variant_of_itself(): void
    {
        $beef = $this->createStoredIngredient('Beef', 0, 0);

        $this->callApi('inventory.ingredient.edit', ['parent_uuid' => $beef->uuid], ['ingredient_uuid' => $beef->uuid])->assertBadRequest();

        $this->assertNull($beef->fresh()->parent_uuid);
    }
}
