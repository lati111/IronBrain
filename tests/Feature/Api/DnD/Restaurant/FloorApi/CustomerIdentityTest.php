<?php

namespace Tests\Feature\Api\DnD\Restaurant\FloorApi;

use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers who knows a customer by which name, and how customers tip. Players know a customer by their hidden name
 * until whoever runs the restaurant reveals the real one. A customer tips for each portion that left them at least as
 * satisfied as they expect: their tip rate of its price for meeting it, twice that for one above it, and so on, never
 * more than their tip cap for an order. The stew costs 5
 */
class CustomerIdentityTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private User $player;
    private Table $table;
    private Recipe $stew;
    private Customer $bilbo;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->player = $this->getRandomUser(['dnd_restaurant.access']);
        $this->grantRestaurantAccess($this->restaurant, $this->player, RestaurantUser::ROLE_PLAYER);
        app(ShiftService::class)->startService($this->restaurant, '12:00');
        $this->restaurant->refresh();

        $this->table = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->bilbo = $this->createTestCustomer($this->restaurant, $this->table, 'Bilbo');
        $this->bilbo->revealed = false;
        $this->bilbo->hidden_name = 'Hooded hobbit';
        $this->bilbo->save();
    }

    private function getFloor(User $user): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($user)
            ->getJson(route('api.dnd.restaurant.floor.get', ['restaurant_uuid' => $this->restaurant->uuid]))->assertOk();
    }

    private function describe(User $user): array
    {
        return collect($this->getFloor($user)->json('data.customers'))->firstWhere('uuid', $this->bilbo->uuid);
    }

    private function editCustomer(array $parameters): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.floor.customer.edit', ['restaurant_uuid' => $this->restaurant->uuid, 'customer_uuid' => $this->bilbo->uuid]), $parameters);
    }

    //| names

    public function test_players_see_the_hidden_name_until_revealed(): void
    {
        $this->assertEquals('Hooded hobbit', $this->describe($this->player)['name']);
        $this->assertEquals('Bilbo', $this->describe($this->getAdminUser())['name']);

        $response = $this->editCustomer(['revealed' => true])->assertOk();

        $this->assertEquals('Bilbo is revealed', $response->json('message'));
        $this->assertEquals('Bilbo', $this->describe($this->player)['name']);
    }

    public function test_a_customer_can_be_hidden_again(): void
    {
        $this->bilbo->revealed = true;
        $this->bilbo->save();

        $response = $this->editCustomer(['revealed' => false])->assertOk();

        $this->assertEquals('Bilbo is hidden as Hooded hobbit', $response->json('message'));
        $this->assertEquals('Hooded hobbit', $this->describe($this->player)['name']);
    }

    public function test_without_a_hidden_name_players_see_a_stranger(): void
    {
        $this->bilbo->hidden_name = null;
        $this->bilbo->save();

        $this->assertEquals(Customer::UNKNOWN_NAME, $this->describe($this->player)['name']);
    }

    public function test_players_dont_see_the_hidden_details(): void
    {
        $description = $this->describe($this->player);

        $this->assertArrayNotHasKey('hidden_name', $description);
        $this->assertArrayNotHasKey('tip_rate', $description);
        $this->assertArrayHasKey('tip_rate', $this->describe($this->getAdminUser()));
    }

    public function test_new_customers_start_hidden(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.floor.customer.create', ['restaurant_uuid' => $this->restaurant->uuid]), [
                'name' => 'Frodo', 'hidden_name' => 'Small traveller', 'tip_expectation' => 4, 'tip_rate' => 10, 'tip_cap' => 3,
            ])->assertCreated();

        $frodo = Customer::find($response->json('data.uuid'));
        $this->assertFalse($frodo->revealed);
        $this->assertEquals(['Small traveller', 4, 10.0, 3.0], [$frodo->hidden_name, $frodo->tip_expectation, $frodo->tip_rate, $frodo->tip_cap]);
    }

    public function test_orders_show_players_the_hidden_name(): void
    {
        $this->assignTestWaiter($this->table, 'Sam');
        $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.order.place', ['restaurant_uuid' => $this->restaurant->uuid]), [
                'table_uuid' => $this->table->uuid, 'items' => [['recipe_uuid' => $this->stew->uuid, 'customer_uuid' => $this->bilbo->uuid]],
            ])->assertCreated();

        $state = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->player)
            ->getJson(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));

        $this->assertEquals('Hooded hobbit', $state->json('data.orders.0.items.0.customer_name'));
    }

    //| tips

    /** Creates an order of the stew for the table, with the satisfaction each portion was served at and who it was for */
    private function createOrder(array $portions): Order
    {
        $order = new Order();
        $order->restaurant_uuid = $this->restaurant->uuid;
        $order->table_uuid = $this->table->uuid;
        $order->service_uuid = $this->restaurant->getCurrentService()?->uuid;
        $order->status = Order::STATUS_FULFILLED;
        $order->ordered_at = $this->restaurant->getNow();
        $order->total_price = 5 * count($portions);
        $order->save();

        foreach ($portions as [$satisfaction, $customer]) {
            $item = new OrderItem();
            $item->order_uuid = $order->uuid;
            $item->recipe_uuid = $this->stew->uuid;
            $item->customer_uuid = $customer?->uuid;
            $item->name = 'Stew';
            $item->price = 5;
            $item->served_at = $this->restaurant->getNow();
            $item->satisfaction = $satisfaction;
            $item->save();
        }

        return $order;
    }

    private function finish(Order $order): Order
    {
        $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.order.finish', ['restaurant_uuid' => $this->restaurant->uuid, 'order_uuid' => $order->uuid]))
            ->assertOk();
        return $order->fresh();
    }

    private function setTipping(int $expectation, float $rate, float $cap): void
    {
        $this->bilbo->tip_expectation = $expectation;
        $this->bilbo->tip_rate = $rate;
        $this->bilbo->tip_cap = $cap;
        $this->bilbo->save();
    }

    public function test_customers_tip_for_each_satisfaction_from_their_expectation_on(): void
    {
        $this->setTipping(3, 10, 100);
        // 5 at 10% for 3 satisfactions from 3 to 5, 5 at 10% for meeting it, and nothing for falling short of it
        $order = $this->finish($this->createOrder([[5, $this->bilbo], [3, $this->bilbo], [2, $this->bilbo]]));

        $this->assertEquals(1.5 + 0.5, $order->tip);
        $this->assertEquals(17.0, $this->restaurant->fresh()->shift_total);
    }

    public function test_the_tip_never_goes_over_the_cap(): void
    {
        $this->setTipping(1, 50, 2);

        $order = $this->finish($this->createOrder([[5, $this->bilbo], [5, $this->bilbo]]));

        $this->assertEquals(2.0, $order->tip);
    }

    public function test_portions_for_the_table_or_unrated_tip_nothing(): void
    {
        $this->setTipping(1, 100, 100);

        $order = $this->finish($this->createOrder([[5, null], [null, $this->bilbo]]));

        $this->assertEquals(0.0, $order->tip);
    }

    public function test_tips_add_to_a_tip_set_by_hand(): void
    {
        $this->setTipping(3, 10, 100);
        $order = $this->createOrder([[3, $this->bilbo]]);
        $order->tip = 1;
        $order->save();

        $this->assertEquals(1.5, $this->finish($order)->tip);
    }

    public function test_each_customer_has_their_own_cap(): void
    {
        $this->setTipping(1, 100, 1);
        $frodo = $this->createTestCustomer($this->restaurant, $this->table, 'Frodo', 2);
        $frodo->tip_expectation = 1;
        $frodo->tip_rate = 100;
        $frodo->tip_cap = 2;
        $frodo->save();

        $order = $this->finish($this->createOrder([[1, $this->bilbo], [1, $frodo]]));

        $this->assertEquals(1.0 + 2.0, $order->tip);
    }
}
