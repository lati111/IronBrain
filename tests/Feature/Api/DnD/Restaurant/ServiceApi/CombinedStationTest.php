<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Work;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers employees working in both prep and cooking, who prep at half speed. The test recipe takes 10 minutes to prep
 */
class CombinedStationTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $stew;
    private Employee $sam;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        app(ShiftService::class)->startService($this->restaurant, '12:00');
        $this->restaurant->refresh();

        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->sam = $this->createTestEmployee($this->restaurant, null, 'Sam');
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function assign(array $parameters): TestResponse
    {
        return $this->callService('employee.assign', $parameters, ['employee_uuid' => $this->sam->uuid]);
    }

    private function stations(): array
    {
        return $this->sam->fresh()->stations ?? [];
    }

    public function test_an_employee_can_work_in_prep_and_cooking_together(): void
    {
        $this->assign(['station' => 'prep'])->assertOk();
        $response = $this->assign(['station' => 'cooking', 'combine' => true])->assertOk();

        $this->assertEquals(['prep', 'cooking'], $this->stations());
        $this->assertEquals('Sam is working at prep and cooking', $response->json('message'));
        $this->assertEquals(['prep', 'cooking'], collect($response->json('data.employees'))->firstWhere('name', 'Sam')['stations']);
    }

    public function test_without_combining_the_employee_moves(): void
    {
        $this->assign(['station' => 'prep'])->assertOk();
        $this->assign(['station' => 'cooking'])->assertOk();

        $this->assertEquals(['cooking'], $this->stations());
    }

    public function test_prepping_goes_at_half_speed_while_also_cooking(): void
    {
        $this->assign(['station' => 'prep'])->assertOk();
        $meal = $this->createTestMeal($this->restaurant, $this->stew);
        $this->callService('meal.prepare', [], ['meal_uuid' => $meal->uuid])->assertOk();
        $this->assign(['station' => 'cooking', 'combine' => true])->assertOk();

        $response = $this->callService('time', ['advance' => 10])->assertOk();
        $this->assertEquals(Meal::STATUS_PLANNED, $meal->fresh()->status);
        $this->assertEquals(5.0, Work::where('meal_uuid', $meal->uuid)->first()->progress);
        $this->assertEquals(0.5, $response->json('data.stations.prep.speed'));

        $this->callService('time', ['advance' => 10])->assertOk();
        $this->assertEquals(Meal::STATUS_PREPPED, $meal->fresh()->status);
    }

    public function test_the_task_tells_about_both(): void
    {
        $this->assign(['station' => 'prep'])->assertOk();
        $response = $this->assign(['station' => 'cooking', 'combine' => true])->assertOk();

        $this->assertEquals('Prep (idle), and cooking (idle)', collect($response->json('data.employees'))->firstWhere('name', 'Sam')['task']);
    }

    public function test_leaving_one_area_keeps_the_other(): void
    {
        $this->assign(['station' => 'prep'])->assertOk();
        $this->assign(['station' => 'cooking', 'combine' => true])->assertOk();

        $this->assign(['leave' => 'prep'])->assertOk();
        $this->assertEquals(['cooking'], $this->stations());

        $this->assign([])->assertOk();
        $this->assertEquals([], $this->stations());
    }

    public function test_only_prep_and_cooking_can_be_combined(): void
    {
        $this->assign(['station' => 'cleaning'])->assertOk();

        $response = $this->assign(['station' => 'prep', 'combine' => true]);

        $response->assertBadRequest();
        $this->assertEquals('Only prep and cooking can be worked together', $response->json('message'));
        $this->assertEquals(['cleaning'], $this->stations());
    }

    public function test_combining_when_idle_just_assigns(): void
    {
        $this->assign(['station' => 'cooking', 'combine' => true])->assertOk();

        $this->assertEquals(['cooking'], $this->stations());
    }

    public function test_waiting_a_table_takes_them_out_of_both(): void
    {
        $this->assign(['station' => 'prep'])->assertOk();
        $this->assign(['station' => 'cooking', 'combine' => true])->assertOk();
        $table = $this->createTestTable($this->restaurant, null, 2, 'Booth');

        $this->callService('table.waiter', ['employee_uuid' => $this->sam->uuid], ['table_uuid' => $table->uuid])->assertOk();

        $this->assertEquals([], $this->stations());
    }
}
