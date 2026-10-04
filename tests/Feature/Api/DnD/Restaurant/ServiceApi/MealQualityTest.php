<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers meals that need no cooking, the quality of finished meals, and plated portions keeping the meal's quality
 * and freshness. The test recipe takes 10 minutes to prep, is fresh for 30 minutes once served, and good for 6 hours.
 * The clock starts at 12:00, with one employee at prep
 */
class MealQualityTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '12:00';
        $this->restaurant->save();

        $this->recipe = $this->createTestRecipe($this->restaurant, 'Salad');
        $this->createTestEmployee($this->restaurant, WorkStation::PREP, 'Cook');
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function mealAction(string $action, Meal $meal, array $parameters = []): TestResponse
    {
        return $this->callService('meal.' . $action, $parameters, ['meal_uuid' => $meal->uuid]);
    }

    private function advance(int $minutes): TestResponse
    {
        return $this->callService('time', ['advance' => $minutes])->assertOk();
    }

    private function describe(TestResponse $response, Meal $meal): array
    {
        return collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid);
    }

    /** Creates a meal that finished cooking 5 minutes ago and stays good for another 6 hours */
    private function createCookedMeal(): Meal
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_COOKING);
        $meal->portions_left = 2;
        $meal->cooking_ends_at = $this->restaurant->getNow()->subMinutes(5);
        $meal->expires_at = $this->restaurant->getNow()->addHours(6);
        $meal->cooking_settled = true;
        $meal->equipment = [];
        $meal->save();
        return $meal;
    }

    //| meals without cooking

    public function test_meals_without_cooking_time_are_ready_to_serve_once_prepped(): void
    {
        $this->recipe->cooking_time = 0;
        $this->recipe->save();
        $meal = $this->createTestMeal($this->restaurant, $this->recipe);

        $this->mealAction('prepare', $meal)->assertOk();
        $response = $this->advance(10);

        $meal->refresh();
        $this->assertEquals(Meal::STATUS_SERVING, $meal->status);
        $this->assertNull($meal->cooker_uuid);
        // Fresh for 30 minutes from being ready, as that is sooner than the 6 hours it would stay good
        $this->assertEquals('12:40', $meal->expires_at->format('H:i'));
        $this->assertTrue($this->describe($response, $meal)['finished']);
    }

    public function test_their_prep_containers_are_emptied(): void
    {
        $this->recipe->cooking_time = 0;
        $this->recipe->save();
        $bowl = $this->createTestEquipment($this->restaurant, 1, 'Bowl', false);
        $this->ownEquipment($this->restaurant, $bowl, 1);
        $this->addRecipeEquipment($this->recipe, $bowl, 1, EquipmentStage::PREP, true);

        $this->callService('meal.plan', ['recipe_uuid' => $this->recipe->uuid])->assertCreated();
        $meal = Meal::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->mealAction('prepare', $meal)->assertOk();
        $this->advance(10);

        $this->assertEquals(0, $bowl->fresh()->amount_in_use);
        $this->assertEquals(1, $bowl->fresh()->amount_dirty);
    }

    public function test_meals_with_cooking_time_still_wait_for_a_cooker(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe);

        $this->mealAction('prepare', $meal)->assertOk();
        $this->advance(10);

        $this->assertEquals(Meal::STATUS_PREPPED, $meal->fresh()->status);
    }

    //| quality

    public function test_finished_meals_get_a_quality(): void
    {
        $meal = $this->createCookedMeal();

        $response = $this->mealAction('quality', $meal, ['quality' => 17]);

        $response->assertOk();
        $this->assertEquals(17, $meal->fresh()->quality);
        $this->assertEquals(17, $this->describe($response, $meal)['quality']);
    }

    public function test_unfinished_meals_have_no_quality_yet(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);

        $response = $this->mealAction('quality', $meal, ['quality' => 17]);

        $response->assertBadRequest();
        $this->assertEquals('Only finished meals have a quality', $response->json('message'));
        $this->assertNull($meal->fresh()->quality);
    }

    public function test_the_quality_is_a_whole_number(): void
    {
        $meal = $this->createCookedMeal();

        $this->mealAction('quality', $meal, ['quality' => 1.5])->assertBadRequest();
        $this->mealAction('quality', $meal, ['quality' => 'great'])->assertBadRequest();
    }

    //| plated portions

    public function test_plates_from_the_cooker_keep_the_quality_and_spoil_as_if_served(): void
    {
        $meal = $this->createCookedMeal();
        $this->mealAction('quality', $meal, ['quality' => 12])->assertOk();

        $response = $this->mealAction('plate', $meal);

        $response->assertOk();
        $plate = ServedMeal::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->assertEquals(12, $plate->quality);
        $this->assertEquals('12:30', $plate->expires_at->format('H:i'));
        $this->assertEquals(['quality' => 12, 'expires_in_minutes' => 30, 'spoiled' => false],
            array_intersect_key($response->json('data.plates.0'), array_flip(['quality', 'expires_in_minutes', 'spoiled'])));
    }

    public function test_plates_from_the_serving_area_keep_its_freshness(): void
    {
        $meal = $this->createCookedMeal();
        $this->mealAction('serve', $meal)->assertOk();
        $this->advance(20);

        $this->mealAction('plate', $meal)->assertOk();

        // Served at 12:00 and fresh for 30 minutes, rather than fresh for another 30 from plating at 12:20
        $plate = ServedMeal::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->assertEquals('12:30', $plate->expires_at->format('H:i'));
    }

    public function test_plates_spoil_once_their_time_passes(): void
    {
        $meal = $this->createCookedMeal();
        $this->mealAction('plate', $meal)->assertOk();

        $response = $this->advance(30);

        $this->assertTrue($response->json('data.plates.0.spoiled'));
    }
}
