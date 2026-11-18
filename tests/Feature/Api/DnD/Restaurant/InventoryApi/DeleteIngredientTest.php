<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\RecipeByproduct;
use App\Models\DnD\Restaurant\RecipeIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\StorageIngredient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage as FileStorage;
use Tests\Feature\Api\AbstractApiFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

class DeleteIngredientTest extends AbstractApiFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Ingredient $ingredient;

    public function setUp(): void
    {
        parent::setUp();
        FileStorage::fake();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->ingredient = $this->createTestIngredient($this->restaurant, 1, 'Potato');
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(?Restaurant $restaurant = null, ?Ingredient $ingredient = null): string
    {
        return route('api.dnd.restaurant.inventory.ingredient.delete', [
            'restaurant_uuid' => ($restaurant ?? $this->restaurant)->uuid,
            'ingredient_uuid' => ($ingredient ?? $this->ingredient)->uuid,
        ]);
    }

    protected function getModel(): Ingredient
    {
        return new Ingredient();
    }

    public function test_ingredient_is_deleted_along_with_its_links_and_icon(): void
    {
        $icon = FileStorage::putFile('modules/dnd/restaurant/ingredient', UploadedFile::fake()->image('potato.png'));
        $this->ingredient->icon = $icon;
        $this->ingredient->save();

        $storage = $this->createTestStorage($this->restaurant);
        $this->storeIngredient($storage, $this->ingredient, 3);
        $recipe = $this->createTestRecipe($this->restaurant);
        $this->addRecipeIngredient($recipe, $this->ingredient, 2);
        $this->addRecipeByproduct($recipe, $this->ingredient, 1);

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute());

        $response->assertOk();
        $this->assertFalse(Ingredient::where('uuid', $this->ingredient->uuid)->exists());
        $this->assertFalse(StorageIngredient::where('ingredient_uuid', $this->ingredient->uuid)->exists());
        $this->assertFalse(RecipeIngredient::where('ingredient_uuid', $this->ingredient->uuid)->exists());
        $this->assertFalse(RecipeByproduct::where('ingredient_uuid', $this->ingredient->uuid)->exists());
        FileStorage::assertMissing($icon);
        $this->assertEquals([], $response->json('data.inventory.ingredients'));
    }

    public function test_ingredient_of_another_restaurant_is_not_found(): void
    {
        $otherIngredient = $this->createTestIngredient($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute(null, $otherIngredient));

        $response->assertNotFound();
        $this->assertApiMessage('Ingredient not found', $response);
        $this->assertTrue(Ingredient::where('uuid', $otherIngredient->uuid)->exists());
    }

    public function test_restaurant_without_access_is_not_found(): void
    {
        $restaurant = $this->createTestRestaurant($this->getRandomUser(), 'Someone else');

        $response = $this->getHttpClient($this->getOperationUser())->post($this->getRoute($restaurant));

        $response->assertNotFound();
        $this->assertApiMessage('Restaurant not found', $response);
    }
}
