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
 * Covers employees working through the queues of the prep and cleaning areas. The test recipe takes 10 minutes to
 * prep, and test equipment takes 5 minutes to clean per piece. The clock starts at 08:00
 */
class WorkTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '08:00';
        $this->restaurant->save();

        $this->recipe = $this->createTestRecipe($this->restaurant, 'Stew');
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->post(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    /** Plans a meal and queues it for prep */
    private function queueMeal(): Meal
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe);
        $this->callService('meal.prepare', [], ['meal_uuid' => $meal->uuid])->assertOk();
        return $meal;
    }

    private function advance(int $minutes): TestResponse
    {
        return $this->callService('time', ['advance' => $minutes])->assertOk();
    }

    private function assign(Employee $employee, ?WorkStation $station): TestResponse
    {
        return $this->callService('employee.assign', ['station' => $station?->value], ['employee_uuid' => $employee->uuid]);
    }

    private function getProgress(Meal $meal): float
    {
        return Work::where('meal_uuid', $meal->uuid)->first()->progress;
    }

    //| working

    public function test_nothing_is_prepped_without_staff(): void
    {
        $meal = $this->queueMeal();

        $response = $this->advance(60);

        $this->assertEquals(Meal::STATUS_PLANNED, $meal->fresh()->status);
        $this->assertNull($response->json('data.stations.prep.jobs.0.minutes_left'));
    }

    public function test_the_speed_of_the_staff_adds_up(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::PREP, 'Sam');
        $this->createTestEmployee($this->restaurant, WorkStation::PREP, 'Rosie');
        $meal = $this->queueMeal();

        $this->advance(4);
        $this->assertEquals(Meal::STATUS_PLANNED, $meal->fresh()->status);

        $this->advance(1);
        $this->assertEquals(Meal::STATUS_PREPPED, $meal->fresh()->status);
    }

    public function test_the_queue_is_worked_in_order_carrying_time_over(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::PREP);
        $first = $this->queueMeal();
        $second = $this->queueMeal();

        $response = $this->advance(15);

        $this->assertEquals(Meal::STATUS_PREPPED, $first->fresh()->status);
        $this->assertEquals(Meal::STATUS_PLANNED, $second->fresh()->status);
        $this->assertEquals(5.0, $this->getProgress($second));
        $this->assertEquals(5, $response->json('data.stations.prep.jobs.0.minutes_left'));
    }

    public function test_waiting_jobs_count_the_jobs_before_them(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::PREP, 'Sam', prepSpeed: 2);
        $this->queueMeal();
        $this->queueMeal();

        $response = $this->advance(1);

        $this->assertEquals([4, 9], array_column($response->json('data.stations.prep.jobs'), 'minutes_left'));
    }

    public function test_staff_can_join_and_leave_during_service(): void
    {
        $sam = $this->createTestEmployee($this->restaurant, WorkStation::PREP, 'Sam');
        $rosie = $this->createTestEmployee($this->restaurant, null, 'Rosie');
        $meal = $this->queueMeal();

        $this->advance(4);
        $this->assign($rosie, WorkStation::PREP)->assertOk();
        $this->assertEquals(4.0, $this->getProgress($meal));

        $this->advance(2);
        $this->assertEquals(8.0, $this->getProgress($meal));

        $this->assign($sam, null)->assertOk();
        $this->assign($rosie, null)->assertOk();
        $this->advance(30);
        $this->assertEquals(8.0, $this->getProgress($meal));
    }

    public function test_each_area_uses_its_own_speed(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING, 'Sam', prepSpeed: 4, cleaningSpeed: 0.5);
        $cup = $this->createTestEquipment($this->restaurant, 1, 'Cup', false);
        $this->ownEquipment($this->restaurant, $cup, 4);
        $cup->amount_dirty = 2;
        $cup->save();

        $this->callService('equipment.clean', ['amount' => 2], ['equipment_uuid' => $cup->uuid])->assertOk();

        // Each cup takes 10 minutes at half speed
        $this->advance(9);
        $this->assertEquals(2, $cup->fresh()->amount_dirty);

        $this->advance(1);
        $this->assertEquals(1, $cup->fresh()->amount_dirty);

        $this->advance(10);
        $this->assertEquals(0, $cup->fresh()->amount_dirty);
    }

    //| cleaning

    /** Gives the restaurant cups to clean, each taking 5 minutes at normal speed */
    private function createDirtyCups(int $dirty): \App\Models\DnD\Restaurant\Equipment
    {
        $cup = $this->createTestEquipment($this->restaurant, 1, 'Cup', false);
        $this->ownEquipment($this->restaurant, $cup, 10);
        $cup->amount_dirty = $dirty;
        $cup->save();
        return $cup;
    }

    public function test_a_piece_is_cleaned_by_a_single_cleaner(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING, 'Sam');
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING, 'Rosie');
        $cup = $this->createDirtyCups(1);

        $response = $this->callService('equipment.clean', ['amount' => 1], ['equipment_uuid' => $cup->uuid]);

        // A second cleaner doesn't make a single cup any quicker, unlike prep
        $this->assertEquals(5, $response->json('data.stations.cleaning.jobs.0.minutes_left'));
        $this->advance(4);
        $this->assertEquals(1, $cup->fresh()->amount_dirty);
        $this->advance(1);
        $this->assertEquals(0, $cup->fresh()->amount_dirty);
    }

    public function test_cleaners_clean_pieces_side_by_side(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING, 'Sam');
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING, 'Rosie');
        $cup = $this->createDirtyCups(3);

        $response = $this->callService('equipment.clean', ['amount' => 3], ['equipment_uuid' => $cup->uuid]);

        // Two cups at once, then the third
        $this->assertEquals([5, 5, 10], array_column($response->json('data.stations.cleaning.jobs'), 'minutes_left'));
        $tasks = collect($response->json('data.employees'))->pluck('task')->all();
        $this->assertEquals(['Cleaning 1 Cup', 'Cleaning 1 Cup'], $tasks);

        $this->advance(5);
        $this->assertEquals(1, $cup->fresh()->amount_dirty);
        $this->advance(5);
        $this->assertEquals(0, $cup->fresh()->amount_dirty);
    }

    public function test_each_cleaner_works_at_their_own_speed(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING, 'Sam', cleaningSpeed: 5);
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING, 'Rosie');
        $cup = $this->createDirtyCups(4);

        $this->callService('equipment.clean', ['amount' => 4], ['equipment_uuid' => $cup->uuid])->assertOk();

        // Sam cleans a cup a minute, so has done the other three while Rosie is still on hers
        $this->advance(3);
        $this->assertEquals(1, $cup->fresh()->amount_dirty);
        $this->advance(2);
        $this->assertEquals(0, $cup->fresh()->amount_dirty);
    }

    //| prioritising

    private function prioritize(string $workUuid): TestResponse
    {
        return $this->callService('work.prioritize', [], ['work_uuid' => $workUuid]);
    }

    public function test_a_prep_job_can_be_prioritized(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::PREP);
        $first = $this->queueMeal();
        $second = $this->queueMeal();

        $this->prioritize(Work::where('meal_uuid', $second->uuid)->first()->uuid)->assertOk();
        $this->advance(10);

        $this->assertEquals(Meal::STATUS_PLANNED, $first->fresh()->status);
        $this->assertEquals(Meal::STATUS_PREPPED, $second->fresh()->status);
    }

    public function test_prioritizing_cleaning_moves_every_piece_of_the_equipment(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::CLEANING);
        $cups = $this->createDirtyCups(2);
        $plates = $this->createTestEquipment($this->restaurant, 1, 'Plate', false);
        $this->ownEquipment($this->restaurant, $plates, 10);
        $plates->amount_dirty = 2;
        $plates->save();
        $this->callService('equipment.clean', ['amount' => 2], ['equipment_uuid' => $cups->uuid])->assertOk();
        $this->callService('equipment.clean', ['amount' => 2], ['equipment_uuid' => $plates->uuid])->assertOk();

        $response = $this->prioritize(Work::where('equipment_uuid', $plates->uuid)->first()->uuid);

        $equipment = array_column($response->json('data.stations.cleaning.jobs'), 'equipment_uuid');
        $this->assertEquals([$plates->uuid, $plates->uuid, $cups->uuid, $cups->uuid], $equipment);
        $this->advance(10);
        $this->assertEquals(0, $plates->fresh()->amount_dirty);
        $this->assertEquals(2, $cups->fresh()->amount_dirty);
    }

    public function test_a_waiter_job_can_be_prioritized(): void
    {
        $sam = $this->createTestEmployee($this->restaurant, null, 'Sam');
        $booth = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $corner = $this->createTestTable($this->restaurant, null, 4, 'Corner');
        $this->createTestCustomer($this->restaurant, $booth, 'Bilbo', 1);
        $this->createTestCustomer($this->restaurant, $corner, 'Frodo', 1);
        $booth->employee_uuid = $sam->uuid;
        $booth->save();
        $corner->employee_uuid = $sam->uuid;
        $corner->save();

        $item = ['recipe_uuid' => $this->recipe->uuid, 'customer_uuid' => null];
        $this->callService('order.place', ['table_uuid' => $booth->uuid, 'items' => [$item]])->assertCreated();
        $this->callService('order.place', ['table_uuid' => $corner->uuid, 'items' => [$item]])->assertCreated();

        $cornerJob = Work::where('table_uuid', $corner->uuid)->first();
        $response = $this->prioritize($cornerJob->uuid);

        $this->assertEquals('Taking an order at Corner', collect($response->json('data.employees'))->firstWhere('name', 'Sam')['task']);
    }

    public function test_job_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $this->createTestEmployee($other, WorkStation::PREP);
        $job = new Work();
        $job->restaurant_uuid = $other->uuid;
        $job->station = WorkStation::PREP;
        $job->work = 10;
        $job->save();

        $this->prioritize($job->uuid)->assertNotFound();
    }

    public function test_turning_the_clock_back_undoes_no_work(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::PREP);
        $meal = $this->queueMeal();
        $this->advance(5);

        $this->callService('time', ['time' => '07:00'])->assertOk();
        $this->assertEquals(5.0, $this->getProgress($meal));

        $this->callService('time', ['time' => '07:05'])->assertOk();
        $this->assertEquals(Meal::STATUS_PREPPED, $meal->fresh()->status);
    }

    public function test_progress_is_given_per_job_and_for_the_whole_queue(): void
    {
        $this->createTestEmployee($this->restaurant, WorkStation::PREP);
        $this->queueMeal();
        $this->queueMeal();

        $response = $this->advance(15);

        // The finished first meal still counts towards the total until the queue is done
        $this->assertEquals(0.75, $response->json('data.stations.prep.progress'));
        $this->assertCount(1, $response->json('data.stations.prep.jobs'));
        $this->assertEquals(0.5, $response->json('data.stations.prep.jobs.0.progress'));

        $response = $this->advance(5);

        $this->assertNull($response->json('data.stations.prep.progress'));
        $this->assertFalse(Work::where('restaurant_uuid', $this->restaurant->uuid)->exists());
    }

    public function test_cooking_meals_give_their_progress(): void
    {
        $stove = $this->createTestCooker($this->restaurant);
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);
        $this->callService('meal.cook', ['cooker_uuid' => $stove->uuid, 'slot' => 1], ['meal_uuid' => $meal->uuid])->assertOk();

        $response = $this->advance(5);

        $this->assertEquals(0.25, collect($response->json('data.meals'))->firstWhere('uuid', $meal->uuid)['cooking_progress']);
    }

    public function test_a_meal_can_only_be_queued_once(): void
    {
        $meal = $this->queueMeal();

        $response = $this->callService('meal.prepare', [], ['meal_uuid' => $meal->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('Stew is already queued for prep', $response->json('message'));
    }

    public function test_cancelling_a_queued_meal_takes_it_out_of_the_queue(): void
    {
        $meal = $this->queueMeal();

        $this->callService('meal.cancel', [], ['meal_uuid' => $meal->uuid])->assertOk();

        $this->assertFalse(Work::where('restaurant_uuid', $this->restaurant->uuid)->exists());
    }

    //| staff

    public function test_employees_show_their_task(): void
    {
        $sam = $this->createTestEmployee($this->restaurant, null, 'Sam');
        $rosie = $this->createTestEmployee($this->restaurant, null, 'Rosie');
        $this->queueMeal();

        $this->assign($sam, WorkStation::PREP)->assertOk();
        $response = $this->assign($rosie, WorkStation::CLEANING);

        $response->assertOk();
        $employees = collect($response->json('data.employees'))->keyBy('name');
        $this->assertEquals('prep', $employees['Sam']['station']);
        $this->assertEquals('Prepping Stew', $employees['Sam']['task']);
        $this->assertEquals('Cleaning (idle)', $employees['Rosie']['task']);

        $response = $this->assign($sam, null);
        $this->assertEquals('Idle', collect($response->json('data.employees'))->firstWhere('name', 'Sam')['task']);
    }

    public function test_only_work_areas_can_be_assigned(): void
    {
        $sam = $this->createTestEmployee($this->restaurant);

        $this->callService('employee.assign', ['station' => 'bar'], ['employee_uuid' => $sam->uuid])->assertBadRequest();
        $this->assertNull($sam->fresh()->station);
    }

    public function test_ending_the_service_sends_the_staff_home(): void
    {
        $sam = $this->createTestEmployee($this->restaurant, WorkStation::CLEANING, 'Sam');
        $cup = $this->createTestEquipment($this->restaurant, 1, 'Cup', false);
        $this->ownEquipment($this->restaurant, $cup, 4);
        $cup->amount_dirty = 2;
        $cup->save();
        $this->callService('equipment.clean', ['amount' => 2], ['equipment_uuid' => $cup->uuid])->assertOk();
        $meal = $this->queueMeal();

        app(ShiftService::class)->endService($this->restaurant->fresh());

        $this->assertNull($sam->fresh()->station);
        $this->assertFalse(Work::where('station', WorkStation::CLEANING->value)->where('restaurant_uuid', $this->restaurant->uuid)->exists());
        $this->assertTrue(Work::where('meal_uuid', $meal->uuid)->exists());
    }
}
