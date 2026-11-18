<?php

namespace Tests\Feature\Api\DnD\Restaurant\RecipeApi;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\RecipeCategory;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers variants of a recipe, whose stats add to their base recipe's and whose lists add to or take away from their
 * base recipe's. The base recipe is a stew selling at 10, taking 2 beef and 1 salt, a knife for prep and any herb
 */
class RecipeVariantTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $stew;
    private Ingredient $beef;
    private Ingredient $salt;
    private Ingredient $chili;
    private Equipment $knife;
    private Equipment $pot;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->beef = $this->createTestIngredient($this->restaurant, 1, 'Beef');
        $this->salt = $this->createTestIngredient($this->restaurant, 1, 'Salt');
        $this->chili = $this->createTestIngredient($this->restaurant, 1, 'Chili');
        $this->knife = $this->createTestEquipment($this->restaurant, 1, 'Knife', false);
        $this->pot = $this->createTestEquipment($this->restaurant, 1, 'Pot', false);

        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->stew->price = 10;
        $this->stew->difficulty = 1;
        $this->stew->category = 'Soup';
        $this->stew->save();
        $this->addRecipeIngredient($this->stew, $this->beef, 2);
        $this->addRecipeIngredient($this->stew, $this->salt, 1);
        $this->addRecipeEquipment($this->stew, $this->knife, 1);
        $link = new RecipeCategory();
        $link->recipe_uuid = $this->stew->uuid;
        $link->category = 'Herb';
        $link->amount = 1;
        $link->save();
    }

    private function callApi(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.inventory.recipe.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function createVariant(array $parameters = []): Recipe
    {
        $response = $this->callApi('create', array_merge(['name' => 'Spicy Stew', 'parent_uuid' => $this->stew->uuid], $parameters));
        $response->assertCreated();
        return Recipe::find($response->json('data.uuid'));
    }

    /** Gets a recipe's ingredients as amounts keyed by ingredient name */
    private function getIngredients(Recipe $recipe): array
    {
        return $recipe->RecipeIngredients()->with('Ingredient')->get()
            ->mapWithKeys(fn ($link) => [$link->Ingredient->name => $link->amount])->sortKeys()->all();
    }

    private function getEquipment(Recipe $recipe): array
    {
        return $recipe->RecipeEquipment()->get()
            ->mapWithKeys(fn ($link) => [Equipment::find($link->equipment_uuid)->name => [$link->amount, $link->stage]])->sortKeys()->all();
    }

    //| stats

    public function test_a_variant_adds_to_the_stats_of_its_base_recipe(): void
    {
        $variant = $this->createVariant(['price' => 2.5, 'difficulty' => 3, 'prep_time' => -15, 'description' => 'Hot']);

        $this->assertEquals($this->stew->uuid, $variant->parent_uuid);
        $this->assertEquals('Spicy Stew', $variant->name);
        $this->assertEquals('Hot', $variant->description);
        $this->assertEquals(12.5, $variant->price);
        $this->assertEquals(4, $variant->difficulty);
        // Stats never go below what a recipe can have
        $this->assertEquals(0, $variant->prep_time);
        $this->assertEquals(1, $variant->portions);
        // The type and category are the base recipe's
        $this->assertEquals('Soup', $variant->category);
        $this->assertEquals($this->stew->fresh()->type, $variant->type);
    }

    public function test_a_variant_starts_as_its_base_recipe(): void
    {
        $variant = $this->createVariant();

        $this->assertEquals(10, $variant->price);
        $this->assertEquals(['Beef' => 2, 'Salt' => 1], $this->getIngredients($variant));
        $this->assertEquals(['Knife' => [1, EquipmentStage::PREP]], $this->getEquipment($variant));
        $this->assertEquals(['Herb' => 1], $variant->RecipeCategories()->pluck('amount', 'category')->all());
    }

    //| lists

    public function test_a_variant_adds_to_and_takes_away_from_the_lists_of_its_base_recipe(): void
    {
        $variant = $this->createVariant([
            // Adding what the base recipe has already adds to its amount
            'ingredients' => [['uuid' => $this->beef->uuid, 'amount' => 1], ['uuid' => $this->chili->uuid, 'amount' => 0.5]],
            'equipment' => [['uuid' => $this->pot->uuid, 'amount' => 1, 'stage' => 'cooking']],
            'categories' => [['category' => 'Spice', 'amount' => 2]],
            'remove' => ['ingredients' => [$this->salt->uuid], 'equipment' => [$this->knife->uuid], 'categories' => ['Herb']],
        ]);

        $this->assertEquals(['Beef' => 3, 'Chili' => 0.5], $this->getIngredients($variant));
        $this->assertEquals(['Pot' => [1, EquipmentStage::COOKING]], $this->getEquipment($variant));
        $this->assertEquals(['Spice' => 2], $variant->RecipeCategories()->pluck('amount', 'category')->all());
        // The base recipe stays as it is
        $this->assertEquals(['Beef' => 2, 'Salt' => 1], $this->getIngredients($this->stew));
    }

    public function test_a_variant_can_take_other_meals(): void
    {
        $side = $this->createTestRecipe($this->restaurant, 'Bread');

        $variant = $this->createVariant(['meals' => [['recipe_uuid' => $side->uuid, 'portions' => 2]]]);

        $this->assertEquals([$side->uuid => 2], $variant->RecipeMeals()->pluck('portions', 'required_recipe_uuid')->all());
    }

    //| changing

    public function test_changing_the_base_recipe_changes_its_variants(): void
    {
        $variant = $this->createVariant(['price' => 2, 'remove' => ['ingredients' => [$this->salt->uuid]]]);

        $this->callApi('edit', [
            'price' => 20,
            'category' => 'Stew',
            'ingredients' => [['uuid' => $this->beef->uuid, 'amount' => 4], ['uuid' => $this->salt->uuid, 'amount' => 1], ['uuid' => $this->chili->uuid, 'amount' => 1]],
        ], ['recipe_uuid' => $this->stew->uuid])->assertOk();

        $variant->refresh();
        $this->assertEquals(22, $variant->price);
        $this->assertEquals('Stew', $variant->category);
        $this->assertEquals(['Beef' => 4, 'Chili' => 1], $this->getIngredients($variant));
    }

    public function test_editing_a_variant_keeps_what_is_left_out(): void
    {
        $variant = $this->createVariant(['price' => 2, 'ingredients' => [['uuid' => $this->chili->uuid, 'amount' => 1]], 'remove' => ['ingredients' => [$this->salt->uuid]]]);

        $this->callApi('edit', ['difficulty' => 2], ['recipe_uuid' => $variant->uuid])->assertOk();

        $variant->refresh();
        $this->assertEquals(12, $variant->price);
        $this->assertEquals(3, $variant->difficulty);
        $this->assertEquals('Spicy Stew', $variant->name);
        $this->assertEquals(['Beef' => 2, 'Chili' => 1], $this->getIngredients($variant));
    }

    public function test_editing_a_variant_can_restore_what_it_took_away(): void
    {
        $variant = $this->createVariant(['remove' => ['ingredients' => [$this->salt->uuid]]]);

        $this->callApi('edit', ['remove' => ['ingredients' => []]], ['recipe_uuid' => $variant->uuid])->assertOk();

        $this->assertEquals(['Beef' => 2, 'Salt' => 1], $this->getIngredients($variant->refresh()));
    }

    public function test_the_inventory_describes_the_changes_of_a_variant(): void
    {
        $response = $this->callApi('create', [
            'name' => 'Spicy Stew', 'parent_uuid' => $this->stew->uuid, 'price' => 2,
            'ingredients' => [['uuid' => $this->chili->uuid, 'amount' => 1]],
            'remove' => ['ingredients' => [$this->salt->uuid]],
        ]);

        $described = collect($response->json('data.inventory.recipes'))->firstWhere('name', 'Spicy Stew');
        $this->assertEquals($this->stew->uuid, $described['parent_uuid']);
        $this->assertEquals(2, $described['variant_changes']['stats']['price']);
        $this->assertEquals(0, $described['variant_changes']['stats']['difficulty']);
        $this->assertEquals([['uuid' => $this->chili->uuid, 'amount' => 1]], $described['variant_changes']['ingredients']);
        $this->assertEquals([$this->salt->uuid], $described['variant_changes']['removed']['ingredients']);
        $this->assertEquals([], $described['variant_changes']['removed']['equipment']);
        $this->assertNull(collect($response->json('data.inventory.recipes'))->firstWhere('name', 'Stew')['variant_changes']);
    }

    //| rules

    public function test_a_variant_cannot_have_variants_of_its_own(): void
    {
        $variant = $this->createVariant();

        $response = $this->callApi('create', ['name' => 'Extra Spicy Stew', 'parent_uuid' => $variant->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('A variant can\'t have variants of its own', $response->json('message'));
    }

    public function test_a_base_recipe_with_variants_cannot_be_deleted(): void
    {
        $variant = $this->createVariant();

        $response = $this->callApi('delete', [], ['recipe_uuid' => $this->stew->uuid]);
        $response->assertBadRequest();
        $this->assertEquals('Delete the variants of Stew first', $response->json('message'));

        // Once its variants are gone, it can be
        $this->callApi('delete', [], ['recipe_uuid' => $variant->uuid])->assertOk();
        $this->callApi('delete', [], ['recipe_uuid' => $this->stew->uuid])->assertOk();
        $this->assertNull(Recipe::find($this->stew->uuid));
    }

    public function test_a_variant_of_another_restaurants_recipe_is_not_found(): void
    {
        $other = $this->createTestRecipe($this->createTestRestaurant($this->getAdminUser(), 'Other'), 'Soup');

        $this->callApi('create', ['name' => 'Spicy Soup', 'parent_uuid' => $other->uuid])->assertNotFound();
    }

    //| cooking

    public function test_a_variant_is_planned_with_its_own_ingredients(): void
    {
        $storage = $this->createTestStorage($this->restaurant);
        $this->storeIngredient($storage, $this->beef, 10);
        $this->storeIngredient($storage, $this->salt, 10);
        $this->storeIngredient($storage, $this->chili, 10);
        $this->ownEquipment($this->restaurant, $this->knife, 1);
        $variant = $this->createVariant([
            'ingredients' => [['uuid' => $this->chili->uuid, 'amount' => 1]],
            'remove' => ['ingredients' => [$this->salt->uuid], 'categories' => ['Herb']],
        ]);

        $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.meal.plan', ['restaurant_uuid' => $this->restaurant->uuid]), ['recipe_uuid' => $variant->uuid])
            ->assertCreated();

        $this->assertEquals($variant->uuid, Meal::where('restaurant_uuid', $this->restaurant->uuid)->first()->recipe_uuid);
        $this->assertEquals(8, $this->getStoredIngredientAmount($storage, $this->beef));
        $this->assertEquals(10, $this->getStoredIngredientAmount($storage, $this->salt));
        $this->assertEquals(9, $this->getStoredIngredientAmount($storage, $this->chili));
    }
}
