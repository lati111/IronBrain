<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Interfaces\Operations\PostOperationInterface;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Traits\Operations\PostSaveOperation;
use Tests\Traits\ValidationTests;

class CreateIngredientTest extends AbstractApiFeatureTester implements PostOperationInterface
{
    use PostSaveOperation, ValidationTests, RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
    }

    protected function getRoute(): string
    {
        return route('api.dnd.restaurant.inventory.ingredient.create', ['restaurant_uuid' => $this->restaurant->uuid]);
    }

    protected function getModel(): Ingredient
    {
        return new Ingredient();
    }

    function getPostParameters(array $overwrites = []): array
    {
        return array_merge([
            'name'       => 'Potato',
            'expires_in' => 14,
            'size'       => 2,
        ], $overwrites);
    }

    //| validation tests

    public function test_name_validation(): void
    {
        $this->assertRequiredValidation('name');
        $this->assertMaxValidation('string', 'name', 64);
    }

    public function test_expires_in_validation(): void
    {
        $this->assertRequiredValidation('expires_in', 'expires in');
        $this->assertIntegerValidation('expires_in', 'expires in');
    }

    public function test_size_validation(): void
    {
        $this->assertRequiredValidation('size');
        $this->assertNumericValidation('size');
    }

    public function test_size_can_be_fractional(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['size' => 0.25]));

        $response->assertCreated();
        $this->assertEquals(0.25, Ingredient::where('uuid', $response->json('data.uuid'))->first()->size);
    }

    public function test_size_must_be_above_zero(): void
    {
        $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters(['size' => 0]))
            ->assertBadRequest();
    }

    //| save tests

    public function test_ingredient_is_saved_with_icon(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $this->getPostParameters([
                'icon' => UploadedFile::fake()->image('potato.png'),
            ]));

        $response->assertCreated();
        $ingredient = Ingredient::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($this->restaurant->uuid, $ingredient->restaurant_uuid);
        $this->assertEquals(14, $ingredient->expires_in);
        $this->assertEquals(2, $ingredient->size);
        FileStorage::assertExists($ingredient->icon);
        $this->assertEquals('Potato', $response->json('data.inventory.ingredients.0.name'));
    }
}
