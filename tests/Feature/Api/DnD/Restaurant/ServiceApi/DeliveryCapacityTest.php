<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Work;
use App\Service\DnD\Restaurant\OrderService;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers a waiter carrying plates to a table: as many as their capacity in one trip, which takes 2 minutes in total
 */
class DeliveryCapacityTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $stew;
    private Table $table;
    private Employee $waiter;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        app(ShiftService::class)->startService($this->restaurant, '12:00');
        $this->restaurant->refresh();

        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->table = $this->createTestTable($this->restaurant, null, 6, 'Booth');
        $this->createTestCustomer($this->restaurant, $this->table, 'Bilbo', 1);
        $this->waiter = $this->assignTestWaiter($this->table, 'Sam');

        // Six entries ordered, which the waiter has taken
        $this->callService('order.place', ['table_uuid' => $this->table->uuid,
            'items' => array_fill(0, 6, ['recipe_uuid' => $this->stew->uuid, 'customer_uuid' => null])])->assertCreated();
        $this->callService('time', ['advance' => 30])->assertOk();
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function deliver(): ServedMeal
    {
        $plate = new ServedMeal();
        $plate->restaurant_uuid = $this->restaurant->uuid;
        $plate->recipe_uuid = $this->stew->uuid;
        $plate->equipment = [];
        $plate->save();
        $this->callService('plate.deliver', ['table_uuid' => $this->table->uuid], ['plate_uuid' => $plate->uuid])->assertOk();
        return $plate;
    }

    private function servedCount(): int
    {
        return OrderItem::whereNotNull('served_at')->count();
    }

    public function test_a_waiter_carries_two_plates_in_one_trip_by_default(): void
    {
        $this->assertEquals(2, $this->waiter->fresh()->capacity);

        $this->deliver();
        $this->deliver();
        $this->assertEquals(1, Work::whereNull('carrier_uuid')->where('table_uuid', $this->table->uuid)->where('work', '>', 0)->count());

        $this->callService('time', ['advance' => OrderService::DELIVERY_MINUTES - 1])->assertOk();
        $this->assertEquals(0, $this->servedCount());
        $this->callService('time', ['advance' => 1])->assertOk();
        $this->assertEquals(2, $this->servedCount());
    }

    public function test_plates_over_the_capacity_take_another_trip(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->deliver();
        }

        $this->callService('time', ['advance' => OrderService::DELIVERY_MINUTES])->assertOk();
        $this->assertEquals(2, $this->servedCount());
        $this->callService('time', ['advance' => OrderService::DELIVERY_MINUTES])->assertOk();
        $this->assertEquals(3, $this->servedCount());
    }

    public function test_the_capacity_is_set_per_employee(): void
    {
        $this->waiter->capacity = 4;
        $this->waiter->save();

        foreach (range(1, 4) as $ignored) {
            $this->deliver();
        }
        $this->callService('time', ['advance' => OrderService::DELIVERY_MINUTES])->assertOk();

        $this->assertEquals(4, $this->servedCount());
    }

    public function test_a_trip_under_way_takes_no_more_plates(): void
    {
        $this->deliver();
        $this->callService('time', ['advance' => 1])->assertOk();

        $this->deliver();
        $this->callService('time', ['advance' => 1])->assertOk();
        $this->assertEquals(1, $this->servedCount());

        $this->callService('time', ['advance' => OrderService::DELIVERY_MINUTES])->assertOk();
        $this->assertEquals(2, $this->servedCount());
    }

    public function test_the_task_names_what_is_carried(): void
    {
        $this->deliver();
        $this->deliver();

        $state = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->getJson(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));

        $this->assertEquals('Bringing Stew, Stew to Booth', collect($state->json('data.employees'))->firstWhere('name', 'Sam')['task']);
    }
}
