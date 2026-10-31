<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers the cooking area: the cookers only cook while someone works there, so meals in them wait for a cook. The
 * stew cooks in 20 minutes, and the service starts at 12:00
 */
class CookingAreaTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $stew;
    private Equipment $stove;
    private Employee $chef;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        app(ShiftService::class)->startService($this->restaurant, '12:00');
        $this->restaurant->refresh();

        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->stove = $this->createTestCooker($this->restaurant);
        $this->chef = $this->createTestEmployee($this->restaurant, null, 'Chef');
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function cookStew(): Meal
    {
        $meal = $this->createTestMeal($this->restaurant, $this->stew, Meal::STATUS_PREPPED);
        $this->callService('meal.cook', ['cooker_uuid' => $this->stove->uuid, 'slot' => 1], ['meal_uuid' => $meal->uuid])->assertOk();
        return $meal;
    }

    private function assignChef(?string $station): TestResponse
    {
        return $this->callService('employee.assign', array_filter(['station' => $station]), ['employee_uuid' => $this->chef->uuid])->assertOk();
    }

    private function getStatus(Meal $meal): string
    {
        return $meal->fresh()->getStatusAt($this->restaurant->fresh()->getNow());
    }

    public function test_nothing_cooks_without_anyone_in_the_cooking_area(): void
    {
        $meal = $this->cookStew();
        $response = $this->callService('time', ['advance' => 30])->assertOk();

        $this->assertEquals(Meal::STATUS_COOKING, $this->getStatus($meal));
        $this->assertEquals(0, $meal->fresh()->cooking_progress);
        $this->assertNull(collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['cooking_minutes_left']);
    }

    public function test_cooking_starts_once_someone_works_in_the_cooking_area(): void
    {
        $meal = $this->cookStew();

        $response = $this->assignChef('cooking');
        $this->assertEquals('Chef is working at cooking', $response->json('message'));
        $this->assertEquals(20, collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['cooking_minutes_left']);
        $this->assertEquals('Cooking', collect($response->json('data.employees'))->firstWhere('uuid', $this->chef->uuid)['task']);

        $this->callService('time', ['advance' => 20])->assertOk();
        $this->assertEquals(Meal::STATUS_DONE, $this->getStatus($meal));
    }

    public function test_cooking_pauses_when_the_cook_leaves_and_keeps_what_was_cooked(): void
    {
        $this->assignChef('cooking');
        $meal = $this->cookStew();
        $this->callService('time', ['advance' => 5])->assertOk();

        $this->assignChef(null);
        $this->callService('time', ['advance' => 30])->assertOk();
        $this->assertEquals(5, $meal->fresh()->cooking_progress);
        $this->assertEquals(Meal::STATUS_COOKING, $this->getStatus($meal));

        // Picks up where it left off, with 15 minutes to go
        $response = $this->assignChef('cooking');
        $this->assertEquals(15, collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['cooking_minutes_left']);
    }

    public function test_a_meal_nobody_cooks_cant_be_skipped(): void
    {
        $meal = $this->cookStew();

        $response = $this->callService('meal.skip', [], ['meal_uuid' => $meal->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('Nobody is cooking Stew', $response->json('message'));
    }

    public function test_a_waiter_moved_into_the_kitchen_stops_waiting(): void
    {
        $table = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $table->employee_uuid = $this->chef->uuid;
        $table->save();

        $this->assignChef('cooking');

        $this->assertNull($table->fresh()->employee_uuid);
        $this->assertEquals(WorkStation::COOKING, $this->chef->fresh()->station);
    }
}
