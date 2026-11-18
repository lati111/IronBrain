<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers employees waiting tables: being assigned, doing nothing else meanwhile, and taking the orders. The clock
 * starts at 12:00
 */
class WaiterTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;
    private Table $booth;
    private Table $corner;
    private Employee $sam;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '12:00';
        $this->restaurant->save();

        $this->recipe = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->booth = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $this->corner = $this->createTestTable($this->restaurant, null, 2, 'Corner');
        $this->sam = $this->createTestEmployee($this->restaurant, null, 'Sam');
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function assignWaiter(Table $table, ?Employee $employee): TestResponse
    {
        return $this->callService('table.waiter', ['employee_uuid' => $employee?->uuid], ['table_uuid' => $table->uuid]);
    }

    private function order(Table $table): TestResponse
    {
        return $this->callService('order.place', [
            'table_uuid' => $table->uuid,
            'items' => [['recipe_uuid' => $this->recipe->uuid, 'customer_uuid' => null]],
        ]);
    }

    //| assigning

    public function test_an_employee_can_wait_several_tables(): void
    {
        $this->assignWaiter($this->booth, $this->sam)->assertOk();
        $response = $this->assignWaiter($this->corner, $this->sam);

        $response->assertOk();
        $employee = collect($response->json('data.employees'))->firstWhere('name', 'Sam');
        $this->assertEqualsCanonicalizing([$this->booth->uuid, $this->corner->uuid], $employee['tables']);
        $this->assertEquals('Waiting Booth, Corner', $employee['task']);
    }

    public function test_a_table_has_a_single_waiter(): void
    {
        $rosie = $this->createTestEmployee($this->restaurant, null, 'Rosie');
        $this->assignWaiter($this->booth, $this->sam)->assertOk();

        $this->assignWaiter($this->booth, $rosie)->assertOk();

        $this->assertEquals($rosie->uuid, $this->booth->fresh()->employee_uuid);
    }

    public function test_waiting_tables_takes_an_employee_out_of_their_work_area(): void
    {
        $this->sam->station = WorkStation::PREP;
        $this->sam->save();

        $this->assignWaiter($this->booth, $this->sam)->assertOk();

        $this->assertNull($this->sam->fresh()->station);
    }

    public function test_working_in_an_area_takes_an_employee_off_their_tables(): void
    {
        $this->assignWaiter($this->booth, $this->sam)->assertOk();

        $this->callService('employee.assign', ['station' => 'cleaning'], ['employee_uuid' => $this->sam->uuid])->assertOk();

        $this->assertNull($this->booth->fresh()->employee_uuid);
        $this->assertEquals(WorkStation::CLEANING, $this->sam->fresh()->station);
    }

    public function test_stopping_work_takes_an_employee_off_their_tables(): void
    {
        $this->assignWaiter($this->booth, $this->sam)->assertOk();

        $this->callService('employee.assign', ['station' => null], ['employee_uuid' => $this->sam->uuid])->assertOk();

        $this->assertNull($this->booth->fresh()->employee_uuid);
    }

    public function test_a_table_can_be_left_without_a_waiter(): void
    {
        $this->assignWaiter($this->booth, $this->sam)->assertOk();

        $this->assignWaiter($this->booth, null)->assertOk();

        $this->assertNull($this->booth->fresh()->employee_uuid);
    }

    public function test_employee_of_another_restaurant_is_not_found(): void
    {
        $other = $this->createTestEmployee($this->createTestRestaurant($this->getAdminUser(), 'Other'));

        $this->assignWaiter($this->booth, $other)->assertNotFound();
    }

    //| taking orders

    public function test_orders_need_a_waiter(): void
    {
        $response = $this->order($this->booth);

        $response->assertBadRequest();
        $this->assertEquals('Nobody is waiting Booth', $response->json('message'));
        $this->assertFalse(Order::where('table_uuid', $this->booth->uuid)->exists());
    }

    public function test_taking_an_order_takes_the_waiter_two_minutes_per_seated_customer(): void
    {
        $this->createTestCustomer($this->restaurant, $this->booth, 'Bilbo', 1);
        $this->createTestCustomer($this->restaurant, $this->booth, 'Frodo', 2);
        $this->createTestCustomer($this->restaurant, $this->booth, 'Merry', 3);
        $this->assignWaiter($this->booth, $this->sam)->assertOk();

        $response = $this->order($this->booth);

        // The clock doesn't move, the waiter takes the order as it does
        $response->assertCreated();
        $this->assertEquals('12:00', $response->json('data.clock.time'));
        $this->assertEquals('taking', $response->json('data.orders.0.status'));
        $this->assertEquals(6, $response->json('data.waiting.0.minutes_left'));
        $this->assertEquals('Taking an order at Booth', collect($response->json('data.employees'))->firstWhere('name', 'Sam')['task']);

        $this->advance(5);
        $this->assertEquals(Order::STATUS_TAKING, Order::where('table_uuid', $this->booth->uuid)->first()->status);

        $this->advance(1);
        $this->assertEquals(Order::STATUS_OPEN, Order::where('table_uuid', $this->booth->uuid)->first()->status);
    }

    public function test_a_waiter_takes_one_job_at_a_time_across_their_tables(): void
    {
        $this->createTestCustomer($this->restaurant, $this->booth, 'Bilbo', 1);
        $this->createTestCustomer($this->restaurant, $this->corner, 'Frodo', 1);
        $this->assignWaiter($this->booth, $this->sam)->assertOk();
        $this->assignWaiter($this->corner, $this->sam)->assertOk();

        $this->order($this->booth)->assertCreated();
        $response = $this->order($this->corner);

        $this->assertEquals([2, 4], array_column($response->json('data.waiting'), 'minutes_left'));
    }

    public function test_jobs_of_a_table_wait_for_a_waiter(): void
    {
        $rosie = $this->createTestEmployee($this->restaurant, null, 'Rosie');
        $this->createTestCustomer($this->restaurant, $this->booth, 'Bilbo', 1);
        $this->assignWaiter($this->booth, $this->sam)->assertOk();
        $this->order($this->booth)->assertCreated();

        // Without a waiter nothing happens, until someone else takes over the table
        $this->assignWaiter($this->booth, null)->assertOk();
        $response = $this->advance(10);
        $this->assertNull($response->json('data.waiting.0.minutes_left'));
        $this->assertEquals(Order::STATUS_TAKING, Order::where('table_uuid', $this->booth->uuid)->first()->status);

        $this->assignWaiter($this->booth, $rosie)->assertOk();
        $this->advance(2);
        $this->assertEquals(Order::STATUS_OPEN, Order::where('table_uuid', $this->booth->uuid)->first()->status);
    }

    public function test_orders_at_an_empty_table_are_taken_straight_away(): void
    {
        $this->assignWaiter($this->booth, $this->sam)->assertOk();

        $response = $this->order($this->booth);

        $this->assertEquals('open', $response->json('data.orders.0.status'));
    }

    public function test_plates_need_a_waiter(): void
    {
        $this->assignWaiter($this->booth, $this->sam)->assertOk();
        $this->order($this->booth)->assertCreated();
        $this->assignWaiter($this->booth, null)->assertOk();

        $meal = $this->createTestMeal($this->restaurant, $this->recipe, \App\Models\DnD\Restaurant\Meal::STATUS_SERVING);
        $meal->portions_left = 1;
        $meal->equipment = [];
        $meal->save();
        $this->callService('meal.plate', [], ['meal_uuid' => $meal->uuid])->assertOk();
        $plate = \App\Models\DnD\Restaurant\ServedMeal::where('restaurant_uuid', $this->restaurant->uuid)->first();

        $response = $this->callService('plate.deliver', ['table_uuid' => $this->booth->uuid], ['plate_uuid' => $plate->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('Nobody is waiting Booth', $response->json('message'));
    }

    private function advance(int $minutes): TestResponse
    {
        return $this->callService('time', ['advance' => $minutes])->assertOk();
    }

    public function test_the_staff_goes_home_at_the_end_of_the_service(): void
    {
        $this->assignWaiter($this->booth, $this->sam)->assertOk();

        app(ShiftService::class)->endService($this->restaurant->fresh());

        $this->assertNull($this->booth->fresh()->employee_uuid);
    }
}
