<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostEditOperation;
use Tests\Traits\ValidationTests;

class EditIngredientTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostEditOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->item = $this->createTestIngredient($this->restaurant, 2, 'Potato');
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.ingredient.edit', [
            'restaurant_uuid'  => $this->restaurant->uuid,
            'ingredient_uuid'  => $this->item->uuid,
        ]);
    }

    protected function getModel(): Ingredient
    {
        return new Ingredient();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'       => 'Sweet Potato',
            'expires_in' => 21,
            'size'       => 1.5,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_expires_in_validation(): void
    {
        $this->assertIntegerValidation('expires_in', 'expires in');
    }

    public function test_size_validation(): void
    {
        $this->assertNumericValidation('size');
    }

    //| save tests

    public function test_icon_is_replaced_and_the_old_one_is_removed(): void
    {
        $oldIcon = FileStorage::putFile('modules/dnd/restaurant/ingredient', UploadedFile::fake()->image('old.png'));
        $this->item->icon = $oldIcon;
        $this->item->save();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'icon' => UploadedFile::fake()->image('new.png'),
            ]));

        $response->assertOk();
        $ingredient = Ingredient::where('uuid', $this->item->uuid)->first();
        FileStorage::assertExists($ingredient->icon);
        FileStorage::assertMissing($oldIcon);
    }

    public function test_ingredient_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $otherIngredient = $this->createTestIngredient($other);

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.inventory.ingredient.edit', [
                'restaurant_uuid' => $this->restaurant->uuid,
                'ingredient_uuid' => $otherIngredient->uuid,
            ]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Ingredient not found', $response);
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post(
            route('api.dnd.restaurant.inventory.ingredient.edit', [
                'restaurant_uuid' => $restaurant->uuid,
                'ingredient_uuid' => $this->item->uuid,
            ]),
            $this->getPostParameters(),
        );

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
