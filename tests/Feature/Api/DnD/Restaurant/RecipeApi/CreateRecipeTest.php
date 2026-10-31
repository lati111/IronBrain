<?php

namespace Tests\Feature\Api\DnD\Restaurant\RecipeApi;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeByproduct;
use App\Models\DnD\Restaurant\RecipeEquipment;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateRecipeTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.recipe.create', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Recipe
    {
        return new Recipe();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'              => 'Potato Stew',
            'price'             => 4.5,
            'portions'          => 4,
            'difficulty'        => 12,
            'prep_time'         => 10,
            'cooking_time'      => 30,
            'fresh_for'         => 60,
            'good_for'          => 8,
            'expires_in'        => 3,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_numeric_field_validation(): void
    {
        $this->assertRequiredValidation('price');
        $this->assertNumericValidation('price');
        $this->assertRequiredValidation('prep_time', 'prep time');
        $this->assertIntegerValidation('cooking_time', 'cooking time');
        $this->assertRequiredValidation('difficulty');
        $this->assertIntegerValidation('portions');
    }

    public function test_portions_must_be_at_least_one(): void
    {
        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['portions' => 0]))
            ->assertBadRequest();
    }

    public function test_portions_default_to_one(): void
    {
        $params = $this->getPostParameters();
        unset($params['portions']);

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(), $params);

        $response->assertCreated();
        $this->assertEquals(1, Recipe::where('uuid', $response->json('data.uuid'))->value('portions'));
    }

    public function test_byproducts_are_saved(): void
    {
        $peel = $this->createTestIngredient($this->restaurant, 1, 'Potato Peel');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'byproducts' => [['uuid' => $peel->uuid, 'amount' => 2]],
            ]));

        $response->assertCreated();
        $recipe = Recipe::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals(2, RecipeByproduct::where('recipe_uuid', $recipe->uuid)->where('ingredient_uuid', $peel->uuid)->value('amount'));
        $this->assertEquals([['uuid' => $peel->uuid, 'amount' => 2]], $response->json('data.inventory.recipes.0.byproducts'));
    }

    public function test_cookers_cannot_be_part_of_a_recipe(): void
    {
        $stove = $this->createTestCooker($this->restaurant);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'equipment' => [['uuid' => $stove->uuid, 'amount' => 1]],
            ]));

        $response->assertBadRequest();
        $this->assertApiMessage('Cookers can\'t be part of a recipe', $response);
        $this->assertFalse(Recipe::where('restaurant_uuid', $this->restaurant->uuid)->exists());
    }

    public function test_byproduct_of_another_restaurant_is_not_found(): void
    {
        $otherIngredient = $this->createTestIngredient($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'byproducts' => [['uuid' => $otherIngredient->uuid, 'amount' => 1]],
            ]));

        $response->assertNotFound();
        $this->assertApiMessage('Ingredient not found', $response);
    }

    //| save tests

    public function test_recipe_is_saved_with_components(): void
    {
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'ingredients' => [['uuid' => $potato->uuid, 'amount' => 3]],
                'equipment'   => [['uuid' => $pot->uuid, 'amount' => 1]],
            ]));

        $response->assertCreated();
        $recipe = Recipe::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($this->restaurant->uuid, $recipe->restaurant_uuid);
        $this->assertEquals(3, RecipeIngredient::where('recipe_uuid', $recipe->uuid)->where('ingredient_uuid', $potato->uuid)->value('amount'));
        $this->assertEquals(1, RecipeEquipment::where('recipe_uuid', $recipe->uuid)->where('equipment_uuid', $pot->uuid)->value('amount'));
        $this->assertEquals([['uuid' => $potato->uuid, 'amount' => 3]], $response->json('data.inventory.recipes.0.ingredients'));
    }

    public function test_ingredient_and_byproduct_amounts_can_be_fractional(): void
    {
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $peel = $this->createTestIngredient($this->restaurant, 1, 'Peel');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'ingredients' => [['uuid' => $potato->uuid, 'amount' => 0.5]],
                'byproducts'  => [['uuid' => $peel->uuid, 'amount' => 0.25]],
            ]));

        $response->assertCreated();
        $recipe = Recipe::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals(0.5, RecipeIngredient::where('recipe_uuid', $recipe->uuid)->value('amount'));
        $this->assertEquals(0.25, RecipeByproduct::where('recipe_uuid', $recipe->uuid)->value('amount'));
    }

    public function test_equipment_is_saved_with_its_stage_and_container(): void
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'equipment' => [['uuid' => $pot->uuid, 'amount' => 1, 'stage' => 'cooking', 'container' => true]],
            ]));

        $response->assertCreated();
        $link = RecipeEquipment::where('recipe_uuid', $response->json('data.uuid'))->first();
        $this->assertSame(EquipmentStage::COOKING, $link->stage);
        $this->assertTrue($link->container);
        $this->assertEquals('cooking', $response->json('data.inventory.recipes.0.equipment.0.stage'));
    }

    public function test_equipment_stage_defaults_to_prep(): void
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'equipment' => [['uuid' => $pot->uuid, 'amount' => 1]],
            ]));

        $response->assertCreated();
        $link = RecipeEquipment::where('recipe_uuid', $response->json('data.uuid'))->first();
        $this->assertSame(EquipmentStage::PREP, $link->stage);
        $this->assertFalse($link->container);
    }

    public function test_serving_equipment_cannot_be_a_container(): void
    {
        $plate = $this->createTestEquipment($this->restaurant, 1, 'Plate');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'equipment' => [['uuid' => $plate->uuid, 'amount' => 1, 'stage' => 'serving', 'container' => true]],
            ]));

        $response->assertBadRequest();
        $this->assertApiMessage('Equipment used for serving can\'t be a container', $response);
    }

    public function test_equipment_stage_must_exist(): void
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot');

        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'equipment' => [['uuid' => $pot->uuid, 'amount' => 1, 'stage' => 'juggling']],
            ]))
            ->assertBadRequest();
    }

    public function test_equipment_amounts_must_be_whole(): void
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot');

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'equipment' => [['uuid' => $pot->uuid, 'amount' => 0.5]],
            ]));

        $response->assertBadRequest();
    }

    public function test_component_amount_must_be_positive(): void
    {
        $potato = $this->createTestIngredient($this->restaurant);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'ingredients' => [['uuid' => $potato->uuid, 'amount' => 0]],
            ]));

        $response->assertBadRequest();
    }

    public function test_ingredient_of_another_restaurant_is_not_found(): void
    {
        $otherIngredient = $this->createTestIngredient($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'ingredients' => [['uuid' => $otherIngredient->uuid, 'amount' => 1]],
            ]));

        $response->assertNotFound();
        $this->assertApiMessage('Ingredient not found', $response);
        $this->assertFalse(Recipe::where('restaurant_uuid', $this->restaurant->uuid)->exists());
    }

    public function test_equipment_of_another_restaurant_is_not_found(): void
    {
        $otherEquipment = $this->createTestEquipment($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'equipment' => [['uuid' => $otherEquipment->uuid, 'amount' => 1]],
            ]));

        $response->assertNotFound();
        $this->assertApiMessage('Equipment not found', $response);
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.inventory.recipe.create', ['restaurant_uuid' => $restaurant->uuid]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
