<?php

namespace Tests\Feature\Api\DnD\Restaurant\InventoryApi;

use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\PileIngredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\StorageIngredient;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers stored ingredients going off as the days pass: kept in stacks by how many days they have left, aged by the
 * expiration multiplier of their storage space, and thrown away once they expire. Test ingredients last 7 days
 */
class IngredientExpiryTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Ingredient $potato;
    private Storage $cellar;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->cellar = $this->createTestStorage($this->restaurant, 100, 0, 'Cellar');
    }

    private function callInventory(string $route, array $parameters = [], string $method = 'post'): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->$method(route('api.dnd.restaurant.inventory.' . $route, ['restaurant_uuid' => $this->restaurant->uuid]), $parameters);
    }

    private function advanceDay(): TestResponse
    {
        return $this->callInventory('advance-day')->assertOk();
    }

    private function transfer(array $parameters): TestResponse
    {
        return $this->callInventory('transfer', array_merge(['type' => 'ingredient', 'item_uuid' => $this->potato->uuid], $parameters));
    }

    /** The days left of each stack of the potato in a storage space, soonest to expire first */
    private function getStacks(Storage $storage): array
    {
        return StorageIngredient::where('storage_uuid', $storage->uuid)->where('ingredient_uuid', $this->potato->uuid)
            ->orderBy('days_left')->get()->map(fn (StorageIngredient $stack) => [$stack->days_left, $stack->amount])->all();
    }

    //| stacks

    public function test_ingredients_are_stored_fresh(): void
    {
        $this->transfer(['amount' => 3, 'to_storage_uuid' => $this->cellar->uuid])->assertOk();

        $this->assertEquals([[7.0, 3.0]], $this->getStacks($this->cellar));
    }

    public function test_stacks_of_different_freshness_are_kept_apart(): void
    {
        $this->storeIngredient($this->cellar, $this->potato, 2, 3);

        $this->transfer(['amount' => 3, 'to_storage_uuid' => $this->cellar->uuid])->assertOk();
        $this->transfer(['amount' => 1, 'to_storage_uuid' => $this->cellar->uuid, 'days_left' => 3])->assertOk();

        $this->assertEquals([[3.0, 3.0], [7.0, 3.0]], $this->getStacks($this->cellar));
    }

    public function test_moving_a_stack_keeps_its_freshness(): void
    {
        $pantry = $this->createTestStorage($this->restaurant, 100, 1, 'Pantry');
        $old = $this->storeIngredient($this->cellar, $this->potato, 2, 3);
        $this->storeIngredient($this->cellar, $this->potato, 5);

        $this->transfer(['amount' => 1, 'from_storage_uuid' => $this->cellar->uuid, 'to_storage_uuid' => $pantry->uuid, 'stack_uuid' => $old->uuid])->assertOk();

        $this->assertEquals([[3.0, 1.0]], $this->getStacks($pantry));
        $this->assertEquals([[3.0, 1.0], [7.0, 5.0]], $this->getStacks($this->cellar));
    }

    public function test_contents_are_sorted_by_name_and_then_freshness(): void
    {
        $apple = $this->createTestIngredient($this->restaurant, 1, 'Apple');
        $this->storeIngredient($this->cellar, $this->potato, 1, 6);
        $this->storeIngredient($this->cellar, $this->potato, 1, 2);
        $this->storeIngredient($this->cellar, $apple, 1, 5);

        $items = $this->callInventory('get', [], 'get')->json('data.storages.0.items');

        $this->assertEquals([['Apple', 5], ['Potato', 2], ['Potato', 6]], array_map(fn (array $item) => [$item['name'], $item['days_left']], $items));
    }

    //| days passing

    public function test_a_day_ages_stored_ingredients_by_the_storage_multiplier(): void
    {
        $fridge = $this->createTestStorage($this->restaurant, 100, 1, 'Fridge');
        $fridge->expiration_multiplier = 0.5;
        $fridge->save();
        $this->storeIngredient($this->cellar, $this->potato, 1);
        $this->storeIngredient($fridge, $this->potato, 1);

        $response = $this->advanceDay();

        $this->assertEquals('1492-03-02', $response->json('data.inventory.date'));
        $this->assertEquals([[6.0, 1.0]], $this->getStacks($this->cellar));
        $this->assertEquals([[6.5, 1.0]], $this->getStacks($fridge));
    }

    public function test_the_pile_ages_a_whole_day(): void
    {
        $pile = new PileIngredient();
        $pile->restaurant_uuid = $this->restaurant->uuid;
        $pile->ingredient_uuid = $this->potato->uuid;
        $pile->amount = 2;
        $pile->days_left = 7;
        $pile->save();

        $this->advanceDay();

        $this->assertEquals(6.0, $pile->fresh()->days_left);
    }

    public function test_expired_stacks_are_reported_and_thrown_away(): void
    {
        $this->storeIngredient($this->cellar, $this->potato, 4, 1);
        $this->storeIngredient($this->cellar, $this->potato, 2, 3);

        $response = $this->advanceDay();

        $this->assertEquals([['name' => 'Potato', 'amount' => 4, 'location' => 'Cellar']], $response->json('data.expired'));
        $this->assertEquals([[2.0, 2.0]], $this->getStacks($this->cellar));
    }

    public function test_ingredients_that_expire_in_0_days_never_expire(): void
    {
        $water = $this->createTestIngredient($this->restaurant, 1, 'Water');
        $water->expires_in = 0;
        $water->save();
        $this->storeIngredient($this->cellar, $water, 10);

        $response = $this->advanceDay();

        $this->assertEquals([], $response->json('data.expired'));
        $this->assertEquals(10, $this->getStoredIngredientAmount($this->cellar, $water));
        $this->assertNull(collect($response->json('data.inventory.storages.0.items'))->firstWhere('name', 'Water')['days_left']);
    }

    //| cooking

    public function test_cooking_uses_the_ingredients_closest_to_expiring_and_puts_them_back_as_they_were(): void
    {
        $pantry = $this->createTestStorage($this->restaurant, 100, 1, 'Pantry');
        $this->storeIngredient($this->cellar, $this->potato, 3, 6);
        $this->storeIngredient($pantry, $this->potato, 2, 2);
        $recipe = $this->createTestRecipe($this->restaurant, 'Mash');
        $this->addRecipeIngredient($recipe, $this->potato, 3);

        // The pantry comes later, but its potatoes are closer to expiring
        $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.meal.plan', ['restaurant_uuid' => $this->restaurant->uuid]), ['recipe_uuid' => $recipe->uuid])
            ->assertCreated();

        $this->assertEquals([], $this->getStacks($pantry));
        $this->assertEquals([[6.0, 2.0]], $this->getStacks($this->cellar));

        $meal = Meal::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.meal.cancel', ['restaurant_uuid' => $this->restaurant->uuid, 'meal_uuid' => $meal->uuid]))
            ->assertOk();

        $this->assertEquals([[2.0, 2.0]], $this->getStacks($pantry));
        $this->assertEquals([[6.0, 3.0]], $this->getStacks($this->cellar));
    }
}
