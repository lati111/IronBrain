<?php

namespace Tests\Feature\Api\DnD\Restaurant\RecipeApi;

use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeByproduct;
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostEditOperation;
use Tests\Traits\ValidationTests;

class EditRecipeTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostEditOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->item = $this->createTestRecipe($this->restaurant, 'Potato Stew');
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.recipe.edit', [
            'restaurant_uuid' => $this->restaurant->uuid,
            'recipe_uuid'     => $this->item->uuid,
        ]);
    }

    protected function getModel(): Recipe
    {
        return new Recipe();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'              => 'Hearty Potato Stew',
            'price'             => 6.25,
            'portions'          => 6,
            'difficulty'        => 15,
            'prep_time'         => 15,
            'cooking_time'      => 45,
            'fresh_for'         => 90,
            'good_for'          => 12,
            'expires_in'        => 4,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_numeric_field_validation(): void
    {
        $this->assertNumericValidation('price');
        $this->assertIntegerValidation('expires_in', 'expires in');
    }

    //| component tests

    public function test_components_are_replaced_when_given(): void
    {
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $carrot = $this->createTestIngredient($this->restaurant, 1, 'Carrot');
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot');
        $this->addRecipeIngredient($this->item, $potato, 2);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), [
                'ingredients' => [['uuid' => $carrot->uuid, 'amount' => 4]],
                'equipment'   => [['uuid' => $pot->uuid, 'amount' => 1]],
            ]);

        $response->assertOk();
        $this->assertFalse(RecipeIngredient::where('recipe_uuid', $this->item->uuid)->where('ingredient_uuid', $potato->uuid)->exists());
        $this->assertEquals(4, RecipeIngredient::where('recipe_uuid', $this->item->uuid)->where('ingredient_uuid', $carrot->uuid)->value('amount'));
        $this->assertEquals(1, RecipeEquipment::where('recipe_uuid', $this->item->uuid)->count());
    }

    public function test_existing_component_amount_is_updated(): void
    {
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->addRecipeIngredient($this->item, $potato, 2);

        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['ingredients' => [['uuid' => $potato->uuid, 'amount' => 5]]])
            ->assertOk();

        $this->assertEquals(5, RecipeIngredient::where('recipe_uuid', $this->item->uuid)->value('amount'));
        $this->assertEquals(1, RecipeIngredient::where('recipe_uuid', $this->item->uuid)->count());
    }

    public function test_byproducts_are_replaced_without_touching_ingredients(): void
    {
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $peel = $this->createTestIngredient($this->restaurant, 1, 'Potato Peel');
        $this->addRecipeIngredient($this->item, $potato, 2);

        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['byproducts' => [['uuid' => $peel->uuid, 'amount' => 3]]])
            ->assertOk();

        $this->assertEquals(3, RecipeByproduct::where('recipe_uuid', $this->item->uuid)->where('ingredient_uuid', $peel->uuid)->value('amount'));
        $this->assertEquals(2, RecipeIngredient::where('recipe_uuid', $this->item->uuid)->value('amount'));
    }

    public function test_components_are_kept_when_left_out(): void
    {
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->addRecipeIngredient($this->item, $potato, 2);

        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters())
            ->assertOk();

        $this->assertEquals(2, RecipeIngredient::where('recipe_uuid', $this->item->uuid)->value('amount'));
    }

    public function test_components_can_be_cleared_with_a_json_body(): void
    {
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->addRecipeIngredient($this->item, $potato, 2);

        $this->getHttpClient($this->getOperationUser())
            ->postJson($this->getRoute(), ['ingredients' => []])
            ->assertOk();

        $this->assertFalse(RecipeIngredient::where('recipe_uuid', $this->item->uuid)->exists());
    }

    //| failure tests

    public function test_ingredient_of_another_restaurant_is_not_found(): void
    {
        $otherIngredient = $this->createTestIngredient($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), ['ingredients' => [['uuid' => $otherIngredient->uuid, 'amount' => 1]]]);

        $response->assertNotFound();
        $this->assertApiMessage('Ingredient not found', $response);
    }

    public function test_recipe_of_another_restaurant_is_not_found(): void
    {
        $otherRecipe = $this->createTestRecipe($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.inventory.recipe.edit', [
                'restaurant_uuid' => $this->restaurant->uuid,
                'recipe_uuid'     => $otherRecipe->uuid,
            ]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Recipe not found', $response);
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.inventory.recipe.edit', [
                'restaurant_uuid' => $restaurant->uuid,
                'recipe_uuid'     => $this->item->uuid,
            ]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
