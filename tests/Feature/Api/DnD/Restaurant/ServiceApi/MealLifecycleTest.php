<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers a meal going through the kitchen: prepared, cooked, skipped, served or cancelled along the way. The test
 * recipe takes 10 minutes to prep and 20 to cook, stays good for 6 hours and fresh for 30 minutes
 */
class MealLifecycleTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;
    private Equipment $stove;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '08:00';
        $this->restaurant->save();

        $this->recipe = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->stove = $this->createTestCooker($this->restaurant, 2);
    }

    private function act(string $action, Meal $meal, array $parameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.meal.' . $action, [
                'restaurant_uuid' => $this->restaurant->uuid,
                'meal_uuid'       => $meal->uuid,
            ]), $parameters);
    }

    private function cook(Meal $meal, int $slot = 1, ?Equipment $cooker = null): TestResponse
    {
        return $this->act('cook', $meal, ['cooker_uuid' => ($cooker ?? $this->stove)->uuid, 'slot' => $slot]);
    }

    private function advance(int $minutes): void
    {
        $this->restaurant->refresh();
        $this->restaurant->setNow($this->restaurant->getNow()->addMinutes($minutes));
        $this->restaurant->save();
    }

    //| preparing

    public function test_preparing_queues_the_meal_without_passing_time(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe);

        $response = $this->act('prepare', $meal);

        $response->assertOk();
        $this->assertEquals('08:00', $response->json('data.clock.time'));
        $this->assertEquals(Meal::STATUS_PLANNED, $meal->fresh()->status);
        $this->assertEquals($meal->uuid, $response->json('data.stations.prep.jobs.0.meal_uuid'));
    }

    public function test_only_planned_meals_can_be_prepared(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);

        $this->act('prepare', $meal)->assertBadRequest();
        $this->assertEquals('08:00:00', $this->restaurant->fresh()->current_time);
    }

    //| cancelling

    public function test_cancelling_a_planned_meal_returns_its_ingredients(): void
    {
        $storage = $this->createTestStorage($this->restaurant);
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $meal = $this->createTestMeal($this->restaurant, $this->recipe);
        $meal->consumed = [['storage_uuid' => $storage->uuid, 'ingredient_uuid' => $potato->uuid, 'amount' => 2.5]];
        $meal->save();

        $response = $this->act('cancel', $meal);

        $response->assertOk();
        $this->assertEquals('Meal cancelled, ingredients returned', $response->json('message'));
        $this->assertEquals(2.5, $this->getStoredIngredientAmount($storage, $potato));
        $this->assertNull($meal->fresh());
    }

    public function test_cancelling_a_prepped_meal_wastes_its_ingredients(): void
    {
        $storage = $this->createTestStorage($this->restaurant);
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $meal->consumed = [['storage_uuid' => $storage->uuid, 'ingredient_uuid' => $potato->uuid, 'amount' => 2.5]];
        $meal->save();

        $response = $this->act('cancel', $meal);

        $response->assertOk();
        $this->assertEquals('Meal scrapped', $response->json('message'));
        $this->assertEquals(0.0, $this->getStoredIngredientAmount($storage, $potato));
        $this->assertNull($meal->fresh());
    }

    //| cooking

    public function test_cooking_starts_the_timer(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);

        $response = $this->cook($meal, 2);

        $response->assertOk();
        $this->assertEquals('cooking', $response->json('data.meals.0.status'));
        $this->assertEquals(20, $response->json('data.meals.0.cooking_minutes_left'));
        $this->assertEquals(2, $response->json('data.meals.0.cooker_slot'));
        $this->assertEquals(2, $response->json('data.cookers.0.slots'));
    }

    public function test_meal_is_done_once_the_timer_passes(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $this->cook($meal)->assertOk();

        $this->advance(20);
        $response = $this->getService();

        $this->assertEquals('done', $response->json('data.meals.0.status'));
        // Once done, it stays good for the recipe's 6 hours
        $this->assertEquals(360, $response->json('data.meals.0.expires_in_minutes'));
    }

    public function test_a_full_cooker_takes_no_other_meal(): void
    {
        $first = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $second = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $this->cook($first)->assertOk();

        $response = $this->cook($second);

        $response->assertBadRequest();
        $this->assertEquals('That Stove is already in use', $response->json('message'));
    }

    public function test_cooker_slots_are_limited_to_the_pieces_owned(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);

        $response = $this->cook($meal, 3);

        $response->assertBadRequest();
        $this->assertEquals('There is no Stove 3', $response->json('message'));
    }

    public function test_only_cookers_can_cook(): void
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot', false);
        $this->ownEquipment($this->restaurant, $pot, 1);
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);

        $response = $this->cook($meal, 1, $pot);

        $response->assertBadRequest();
        $this->assertEquals('Pot is not a cooker', $response->json('message'));
    }

    public function test_only_prepped_meals_can_be_cooked(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe);

        $this->cook($meal)->assertBadRequest();
    }

    //| skipping & serving

    public function test_skipping_passes_time_until_done(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $this->cook($meal)->assertOk();

        $response = $this->act('skip', $meal);

        $response->assertOk();
        $this->assertEquals('08:20', $response->json('data.clock.time'));
        $this->assertEquals('done', $response->json('data.meals.0.status'));
    }

    public function test_serving_frees_the_cooker_and_shortens_the_timer(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $this->cook($meal)->assertOk();
        $this->advance(20);

        $response = $this->act('serve', $meal);

        $response->assertOk();
        $this->assertEquals('serving', $response->json('data.meals.0.status'));
        $this->assertNull($response->json('data.meals.0.cooker_uuid'));
        $this->assertEquals(30, $response->json('data.meals.0.expires_in_minutes'));

        // The cooker can take another meal straight away
        $next = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $this->cook($next)->assertOk();
    }

    public function test_meals_still_cooking_cannot_be_served(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $this->cook($meal)->assertOk();

        $this->act('serve', $meal)->assertBadRequest();
    }

    public function test_meals_spoil_once_their_timer_passes(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $this->cook($meal)->assertOk();
        $this->advance(20);
        $this->act('serve', $meal)->assertOk();

        $this->advance(30);

        $this->assertTrue($this->getService()->json('data.meals.0.spoiled'));
    }

    //| access

    public function test_meal_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $meal = $this->createTestMeal($other, $this->createTestRecipe($other));

        $response = $this->act('prepare', $meal);

        $response->assertNotFound();
        $this->assertEquals('Meal not found', $response->json('message'));
    }

    private function getService(): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->get(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));
    }
}
