<?php

namespace Tests\Feature\Api\DnD\Restaurant\RecipeApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class DeleteRecipeTest extends AbstractApiFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->recipe = $this->createTestRecipe($this->restaurant, 'Potato Stew');
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(?Restaurant $restaurant = null, ?Recipe $recipe = null): string
    {
        return route('api.dnd.restaurant.inventory.recipe.delete', [
            'restaurant_uuid' => ($restaurant ?? $this->restaurant)->uuid,
            'recipe_uuid'     => ($recipe ?? $this->recipe)->uuid,
        ]);
    }

    protected function getModel(): Recipe
    {
        return new Recipe();
    }

    public function test_recipe_is_deleted_but_its_ingredients_are_kept(): void
    {
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->addRecipeIngredient($this->recipe, $potato, 2);

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertFalse(Recipe::where('uuid', $this->recipe->uuid)->exists());
        $this->assertFalse(RecipeIngredient::where('recipe_uuid', $this->recipe->uuid)->exists());
        $this->assertTrue(Ingredient::where('uuid', $potato->uuid)->exists());
        $this->assertEquals([], $response->json('data.inventory.recipes'));
    }

    public function test_recipe_of_another_restaurant_is_not_found(): void
    {
        $otherRecipe = $this->createTestRecipe($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(null, $otherRecipe));

        $response->assertNotFound();
        $this->assertApiMessage('Recipe not found', $response);
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($restaurant));

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
