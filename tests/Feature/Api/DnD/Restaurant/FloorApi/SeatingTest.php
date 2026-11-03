<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Work;
use App\Service\DnD\Restaurant\OrderService;
use App\Service\DnD\Restaurant\ShiftService;
use App\Service\DnD\Restaurant\WorkService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers seating customers during service: a table's waiter walks them over from the entrance, which takes a minute,
 * and then takes their order, while a table without a waiter seats them straight away
 */
class SeatingTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Table $table;
    private Customer $bilbo;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        app(ShiftService::class)->startService($this->restaurant, '12:00');
        $this->restaurant->refresh();

        $this->table = $this->createTestTable($this->restaurant, null, 2, 'Booth');
        $this->bilbo = $this->createTestCustomer($this->restaurant, null, 'Bilbo');
    }

    private function callApi(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route($route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function seat(Customer $customer, int $seat): TestResponse
    {
        return $this->callApi('api.dnd.restaurant.floor.customer.seat', ['table_uuid' => $this->table->uuid, 'seat' => $seat], ['customer_uuid' => $customer->uuid]);
    }

    public function test_a_table_without_a_waiter_seats_customers_straight_away(): void
    {
        $this->seat($this->bilbo, 1)->assertOk();

        $this->assertEquals($this->table->uuid, $this->bilbo->fresh()->table_uuid);
        $this->assertFalse(Work::where('customer_uuid', $this->bilbo->uuid)->exists());
    }

    public function test_the_waiter_walks_customers_over(): void
    {
        $this->assignTestWaiter($this->table, 'Sam');

        $this->seat($this->bilbo, 1)->assertOk();
        $this->assertNull($this->bilbo->fresh()->table_uuid);

        $this->assertNull($this->bilbo->fresh()->table_uuid);
        $this->callApi('api.dnd.restaurant.service.time', ['advance' => WorkService::SEATING_MINUTES])->assertOk();

        $this->assertEquals([$this->table->uuid, 1], [$this->bilbo->fresh()->table_uuid, $this->bilbo->fresh()->seat]);
        $this->assertFalse(Work::where('customer_uuid', $this->bilbo->uuid)->exists());
    }

    public function test_seating_a_customer_starts_taking_their_order(): void
    {
        $this->assignTestWaiter($this->table, 'Sam');
        $this->seat($this->bilbo, 1)->assertOk();
        $this->assertEquals(0, Order::count());

        // A minute to walk them over, and then the waiter takes the order of the one seated
        $this->callApi('api.dnd.restaurant.service.time', ['advance' => WorkService::SEATING_MINUTES])->assertOk();
        $order = Order::where('table_uuid', $this->table->uuid)->first();
        $this->assertEquals(Order::STATUS_TAKING, $order->status);

        $this->callApi('api.dnd.restaurant.service.time', ['advance' => OrderService::ORDER_MINUTES_PER_CUSTOMER])->assertOk();
        $this->assertEquals(Order::STATUS_TAKEN, $order->fresh()->status);
        $this->assertEquals('12:03', $order->fresh()->ordered_at->format('H:i'));
    }

    public function test_the_whole_seating_takes_a_minute_plus_taking_the_order_in_one_go(): void
    {
        $this->assignTestWaiter($this->table, 'Sam');
        $this->seat($this->bilbo, 1)->assertOk();

        $this->callApi('api.dnd.restaurant.service.time', ['advance' => WorkService::SEATING_MINUTES + OrderService::ORDER_MINUTES_PER_CUSTOMER])->assertOk();

        $this->assertEquals(Order::STATUS_TAKEN, Order::where('table_uuid', $this->table->uuid)->value('status'));
    }

    public function test_an_order_already_being_taken_covers_customers_seated_after(): void
    {
        $this->assignTestWaiter($this->table, 'Sam');
        $this->seat($this->bilbo, 1)->assertOk();
        $this->callApi('api.dnd.restaurant.service.time', ['advance' => WorkService::SEATING_MINUTES])->assertOk();

        $this->seat($this->createTestCustomer($this->restaurant, null, 'Frodo'), 2)->assertOk();
        $this->callApi('api.dnd.restaurant.service.time', ['advance' => 30])->assertOk();

        $this->assertEquals(1, Order::where('table_uuid', $this->table->uuid)->count());
    }

    public function test_seating_at_a_table_without_a_waiter_starts_no_order(): void
    {
        $this->seat($this->bilbo, 1)->assertOk();
        $this->callApi('api.dnd.restaurant.service.time', ['advance' => 30])->assertOk();

        $this->assertEquals(0, Order::count());
    }

    public function test_a_seat_is_kept_for_a_customer_on_their_way(): void
    {
        $this->assignTestWaiter($this->table, 'Sam');
        $this->seat($this->bilbo, 1)->assertOk();

        $response = $this->seat($this->createTestCustomer($this->restaurant, null, 'Frodo'), 1);

        $response->assertBadRequest();
        $this->assertEquals('That seat is already taken', $response->json('message'));
    }

    public function test_seating_a_customer_again_replaces_where_they_were_going(): void
    {
        $this->assignTestWaiter($this->table, 'Sam');
        $this->seat($this->bilbo, 1)->assertOk();

        $this->seat($this->bilbo, 2)->assertOk();

        $this->assertEquals([2], Work::where('customer_uuid', $this->bilbo->uuid)->pluck('seat')->all());
    }

    public function test_the_waiter_is_busy_seating(): void
    {
        $this->assignTestWaiter($this->table, 'Sam');

        $response = $this->seat($this->bilbo, 1);

        $state = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->getJson(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));
        $this->assertEquals('Seating Bilbo at Booth', collect($state->json('data.employees'))->firstWhere('name', 'Sam')['task']);
    }

    //| the entrance

    private function describe(TestResponse $response, Customer $customer): array
    {
        return collect($response->json('data.floor.customers'))->firstWhere('uuid', $customer->uuid);
    }

    public function test_customers_wait_at_the_entrance_for_everyone_to_see(): void
    {
        $response = $this->callApi('api.dnd.restaurant.floor.customer.entrance', ['at_entrance' => true], ['customer_uuid' => $this->bilbo->uuid])->assertOk();

        $this->assertTrue($this->describe($response, $this->bilbo)['at_entrance']);
        $this->assertEquals('12:00', $this->bilbo->fresh()->entered_at->format('H:i'));

        // Seating them takes them away from the entrance
        $response = $this->seat($this->bilbo, 1)->assertOk();
        $this->assertFalse($this->describe($response, $this->bilbo)['at_entrance']);
        $this->assertNull($this->bilbo->fresh()->entered_at);
    }

    public function test_customers_walk_in_at_the_entrance(): void
    {
        $response = $this->callApi('api.dnd.restaurant.floor.customer.create', ['name' => 'Frodo', 'at_entrance' => true])->assertCreated();

        $this->assertTrue($this->describe($response, Customer::find($response->json('data.uuid')))['at_entrance']);
    }

    public function test_unseated_customers_go_back_to_the_entrance_or_leave(): void
    {
        $this->seat($this->bilbo, 1)->assertOk();
        $response = $this->callApi('api.dnd.restaurant.floor.customer.seat', ['to_entrance' => true], ['customer_uuid' => $this->bilbo->uuid])->assertOk();
        $this->assertTrue($this->describe($response, $this->bilbo)['at_entrance']);

        $response = $this->callApi('api.dnd.restaurant.floor.customer.entrance', ['at_entrance' => false], ['customer_uuid' => $this->bilbo->uuid])->assertOk();
        $this->assertFalse($this->describe($response, $this->bilbo)['at_entrance']);
    }

    public function test_seated_customers_cant_be_brought_to_the_entrance(): void
    {
        $this->seat($this->bilbo, 1)->assertOk();

        $this->callApi('api.dnd.restaurant.floor.customer.entrance', ['at_entrance' => true], ['customer_uuid' => $this->bilbo->uuid])->assertBadRequest();
    }
}
