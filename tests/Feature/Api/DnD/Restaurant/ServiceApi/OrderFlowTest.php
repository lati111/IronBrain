<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\OrderService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers an order from being placed at a table, through plating and bringing its portions and the meal being eaten,
 * to it being finished and paid for, or cancelled. The test recipe costs 5. With two customers seated, the waiter
 * takes an order in 4 minutes, and brings a plate in 2. The helpers let the waiter finish before going on
 */
class OrderFlowTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;
    private Table $table;
    private Customer $bilbo;
    private Customer $frodo;
    private Equipment $plate;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->restaurant->current_time = '12:00';
        $this->restaurant->save();

        $this->recipe = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->table = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $this->bilbo = $this->createTestCustomer($this->restaurant, $this->table, 'Bilbo', 1);
        $this->frodo = $this->createTestCustomer($this->restaurant, $this->table, 'Frodo', 2);
        $this->assignTestWaiter($this->table);

        $this->plate = $this->createTestEquipment($this->restaurant, 1, 'Plate', false);
        $this->ownEquipment($this->restaurant, $this->plate, 10);
        $this->addRecipeEquipment($this->recipe, $this->plate, 1, EquipmentStage::SERVING);
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    /** Places an order, and lets the waiter take it */
    private function placeOrder(array $items, ?Table $table = null): TestResponse
    {
        $response = $this->callService('order.place', ['table_uuid' => ($table ?? $this->table)->uuid, 'items' => $items]);
        if ($response->status() === 201) {
            $this->advance(OrderService::ORDER_MINUTES_PER_CUSTOMER * 2);
        }

        return $response;
    }

    private function advance(int $minutes): TestResponse
    {
        return $this->callService('time', ['advance' => $minutes])->assertOk();
    }

    /** An order item of the test recipe, for a customer or for the table */
    private function item(?Customer $customer = null): array
    {
        return ['recipe_uuid' => $this->recipe->uuid, 'customer_uuid' => $customer?->uuid];
    }

    /** Creates a meal that has finished cooking, with the recipe's equipment as planned */
    private function createCookedMeal(int $portions): Meal
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_COOKING);
        $meal->portions_left = $portions;
        $meal->cooking_ends_at = $this->restaurant->getNow()->subMinutes(5);
        $meal->expires_at = $this->restaurant->getNow()->addHours(6);
        $meal->cooking_settled = true;
        $meal->equipment = [['equipment_uuid' => $this->plate->uuid, 'amount' => 1, 'stage' => 'serving', 'container' => false]];
        $meal->save();
        return $meal;
    }

    private function plateMeal(Meal $meal): ServedMeal
    {
        $this->callService('meal.plate', [], ['meal_uuid' => $meal->uuid])->assertOk();
        return ServedMeal::where('restaurant_uuid', $this->restaurant->uuid)->whereNull('order_item_uuid')->latest()->first();
    }

    /** Sends a plate to a table, and lets the waiter bring it */
    private function deliver(ServedMeal $plate, ?Table $table = null): TestResponse
    {
        $response = $this->callService('plate.deliver', ['table_uuid' => ($table ?? $this->table)->uuid], ['plate_uuid' => $plate->uuid]);
        if ($response->isOk()) {
            $this->advance(OrderService::DELIVERY_MINUTES);
        }

        return $response;
    }

    //| placing orders

    public function test_each_item_is_an_entry_and_is_paid_once_finished(): void
    {
        // The same recipe can be ordered several times, each for one customer
        $response = $this->placeOrder([$this->item($this->bilbo), $this->item($this->bilbo), $this->item($this->frodo), $this->item()]);

        $response->assertCreated();
        $order = Order::where('table_uuid', $this->table->uuid)->first();
        $this->assertEquals(4, $order->Items()->count());
        $this->assertEquals(2, $order->Items()->where('customer_uuid', $this->bilbo->uuid)->count());
        $this->assertEquals(1, $order->Items()->whereNull('customer_uuid')->count());
        $this->assertEquals(20.0, $order->total_price);
        // The table ordered once the waiter was done taking it, after 4 minutes
        $this->assertEquals('12:04', $order->ordered_at->format('H:i'));
        $this->assertEquals('Stew', $response->json('data.orders.0.items.0.name'));

        // Nothing is paid until the order is finished
        $this->assertEquals(0.0, $response->json('data.clock.shift_total'));
        $this->assertNull($order->paid_at);
    }

    public function test_items_without_a_customer_are_for_the_table(): void
    {
        $this->placeOrder([$this->item(), $this->item()])->assertCreated();

        $this->assertEquals(2, OrderItem::whereNull('customer_uuid')->count());
    }

    public function test_customers_must_be_seated_at_the_table(): void
    {
        $merry = $this->createTestCustomer($this->restaurant, null, 'Merry');

        $response = $this->placeOrder([$this->item($merry)]);

        $response->assertBadRequest();
        $this->assertEquals('Merry isn\'t seated at Booth', $response->json('message'));
        $this->assertFalse(Order::where('restaurant_uuid', $this->restaurant->uuid)->exists());
        $this->assertEquals(0.0, $this->restaurant->fresh()->shift_total);
    }

    //| taking an order first

    /** Sends the waiter to take an order, without saying what is ordered yet */
    private function startOrder(): Order
    {
        $this->callService('order.place', ['table_uuid' => $this->table->uuid])->assertCreated();
        return Order::where('table_uuid', $this->table->uuid)->latest('ordered_at')->first();
    }

    private function noteOrder(Order $order, array $items): TestResponse
    {
        return $this->callService('order.edit', ['items' => $items], ['order_uuid' => $order->uuid]);
    }

    public function test_an_order_is_taken_before_what_was_ordered_is_noted_down(): void
    {
        $order = $this->startOrder();
        $this->assertEquals(Order::STATUS_TAKING, $order->status);
        $this->assertEquals(0, $order->Items()->count());

        // While the waiter is still at the table, nothing can be noted down yet
        $response = $this->noteOrder($order, [$this->item($this->bilbo)]);
        $response->assertBadRequest();
        $this->assertEquals('The order at Booth is still being taken', $response->json('message'));

        // Once they are done, the order waits for what was ordered
        $this->advance(OrderService::ORDER_MINUTES_PER_CUSTOMER * 2);
        $this->assertEquals(Order::STATUS_TAKEN, $order->fresh()->status);

        // The table ordered once the waiter was done, so that is what its waiting counts from
        $this->assertEquals('12:04', $order->fresh()->ordered_at->format('H:i'));
        $response = $this->advance(3);
        $this->assertEquals(3, collect($response->json('data.orders'))->firstWhere('uuid', $order->uuid)['ordered_minutes_ago']);

        // Noting it down opens it to be served
        $this->noteOrder($order, [$this->item($this->bilbo), $this->item($this->frodo)])->assertOk();
        $order->refresh();
        $this->assertEquals(Order::STATUS_OPEN, $order->status);
        $this->assertEquals(2, $order->Items()->count());
        $this->assertEquals(10, $order->total_price);
    }

    public function test_one_order_is_taken_at_a_table_at_a_time(): void
    {
        $this->startOrder();

        $response = $this->callService('order.place', ['table_uuid' => $this->table->uuid]);
        $response->assertBadRequest();
        $this->assertEquals('An order is already being taken at Booth', $response->json('message'));

        // Still so once it is taken, until what was ordered is noted down
        $this->advance(OrderService::ORDER_MINUTES_PER_CUSTOMER * 2);
        $this->callService('order.place', ['table_uuid' => $this->table->uuid])->assertBadRequest();
    }

    public function test_a_taken_order_can_be_cancelled(): void
    {
        $order = $this->startOrder();
        $this->advance(OrderService::ORDER_MINUTES_PER_CUSTOMER * 2);

        $this->callService('order.cancel', [], ['order_uuid' => $order->uuid])->assertOk();

        $this->assertEquals(Order::STATUS_CANCELLED, $order->fresh()->status);
        // The table can order again
        $this->startOrder();
    }

    public function test_an_order_placed_with_its_items_still_opens_once_taken(): void
    {
        $this->placeOrder([$this->item($this->bilbo)])->assertCreated();

        $this->assertEquals(Order::STATUS_OPEN, Order::where('table_uuid', $this->table->uuid)->value('status'));
    }

    //| plating

    public function test_plating_uses_serving_equipment_and_a_portion(): void
    {
        $meal = $this->createCookedMeal(3);

        $this->plateMeal($meal);

        $this->assertEquals(2, $meal->fresh()->portions_left);
        $this->assertEquals(1, $this->plate->fresh()->amount_in_use);
    }

    public function test_plating_the_last_portion_finishes_the_meal_and_dirties_its_containers(): void
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot', false);
        $this->ownEquipment($this->restaurant, $pot, 1);
        $pot->amount_in_use = 1;
        $pot->save();

        $meal = $this->createCookedMeal(1);
        $meal->equipment = array_merge($meal->equipment, [['equipment_uuid' => $pot->uuid, 'amount' => 1, 'stage' => 'cooking', 'container' => true]]);
        $meal->save();

        $this->plateMeal($meal);

        $this->assertNull($meal->fresh());
        $this->assertEquals(0, $pot->fresh()->amount_in_use);
        $this->assertEquals(1, $pot->fresh()->amount_dirty);
    }

    public function test_only_cooked_meals_can_be_plated(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_PREPPED);

        $this->callService('meal.plate', [], ['meal_uuid' => $meal->uuid])->assertBadRequest();
    }

    //| delivering

    public function test_delivering_serves_the_oldest_waiting_entry_and_fulfills_the_order(): void
    {
        // Taken by 12:04, and the first plate brought by 12:06
        $this->placeOrder([$this->item($this->bilbo), $this->item($this->frodo)]);
        $meal = $this->createCookedMeal(2);

        $this->deliver($this->plateMeal($meal))->assertOk();
        $order = Order::where('table_uuid', $this->table->uuid)->first();
        $this->assertEquals(Order::STATUS_OPEN, $order->status);
        $this->assertEquals(1, $order->Items()->whereNotNull('served_at')->count());

        // The second is sent at 12:21 and arrives at 12:23
        $this->advance(15);
        $this->deliver($this->plateMeal($meal))->assertOk();

        $this->assertEquals(Order::STATUS_FULFILLED, $order->fresh()->status);
        $this->assertEquals('12:23', $order->fresh()->fulfilled_at->format('H:i'));
    }

    public function test_plates_are_on_their_way_until_the_waiter_arrives(): void
    {
        $this->placeOrder([$this->item($this->bilbo)]);
        $plate = $this->plateMeal($this->createCookedMeal(1));

        $response = $this->callService('plate.deliver', ['table_uuid' => $this->table->uuid], ['plate_uuid' => $plate->uuid]);

        $response->assertOk();
        $this->assertTrue($response->json('data.orders.0.items.0.on_the_way'));
        $this->assertFalse($response->json('data.orders.0.items.0.served'));
        $this->assertEquals([], $response->json('data.plates'));
        $this->assertEquals('Bringing Stew to Booth', $response->json('data.waiting.0.task'));
        $this->assertEquals(2, $response->json('data.waiting.0.minutes_left'));

        $response = $this->advance(2);
        $this->assertTrue($response->json('data.orders.0.items.0.served'));
        $this->assertEquals([], $response->json('data.waiting'));
    }

    public function test_a_portion_gets_a_single_plate(): void
    {
        $this->placeOrder([$this->item($this->bilbo)]);
        $meal = $this->createCookedMeal(2);
        $this->callService('plate.deliver', ['table_uuid' => $this->table->uuid], ['plate_uuid' => $this->plateMeal($meal)->uuid])->assertOk();

        $response = $this->callService('plate.deliver', ['table_uuid' => $this->table->uuid], ['plate_uuid' => $this->plateMeal($meal)->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('Booth isn\'t waiting for Stew', $response->json('message'));
    }

    public function test_orders_being_taken_get_no_plates_yet(): void
    {
        $this->callService('order.place', ['table_uuid' => $this->table->uuid, 'items' => [$this->item()]])->assertCreated();
        $plate = $this->plateMeal($this->createCookedMeal(1));

        $this->callService('plate.deliver', ['table_uuid' => $this->table->uuid], ['plate_uuid' => $plate->uuid])->assertBadRequest();
    }

    public function test_plates_only_go_to_tables_waiting_for_them(): void
    {
        $plate = $this->plateMeal($this->createCookedMeal(1));

        $response = $this->deliver($plate);

        $response->assertBadRequest();
        $this->assertEquals('Booth isn\'t waiting for Stew', $response->json('message'));
        $this->assertNull($plate->fresh()->order_item_uuid);
    }

    public function test_scrapping_a_plate_dirties_its_serving_equipment(): void
    {
        $plate = $this->plateMeal($this->createCookedMeal(2));

        $this->callService('plate.scrap', [], ['plate_uuid' => $plate->uuid])->assertOk();

        $this->assertNull($plate->fresh());
        $this->assertEquals(0, $this->plate->fresh()->amount_in_use);
        $this->assertEquals(1, $this->plate->fresh()->amount_dirty);
    }

    //| consuming

    /** Places an order for one portion, and brings a plate for it */
    private function placeServedOrder(): Order
    {
        $this->placeOrder([$this->item()]);
        $this->deliver($this->plateMeal($this->createCookedMeal(1)))->assertOk();
        return Order::where('table_uuid', $this->table->uuid)->first();
    }

    private function orderAction(string $route, Order $order, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->callService('order.' . $route, $parameters, array_merge(['order_uuid' => $order->uuid], $routeParameters));
    }

    public function test_consuming_returns_serving_equipment_dirty_but_keeps_the_order(): void
    {
        $order = $this->placeServedOrder();

        $this->orderAction('consume', $order)->assertOk();

        $this->assertEquals(Order::STATUS_FULFILLED, $order->fresh()->status);
        $this->assertNotNull($order->Items()->first()->consumed_at);
        $this->assertFalse(ServedMeal::where('restaurant_uuid', $this->restaurant->uuid)->exists());
        $this->assertEquals(0, $this->plate->fresh()->amount_in_use);
        $this->assertEquals(1, $this->plate->fresh()->amount_dirty);
    }

    public function test_single_items_can_be_consumed(): void
    {
        $this->placeOrder([$this->item($this->bilbo), $this->item($this->frodo)]);
        $meal = $this->createCookedMeal(2);
        $this->deliver($this->plateMeal($meal))->assertOk();
        $this->deliver($this->plateMeal($meal))->assertOk();
        $order = Order::where('table_uuid', $this->table->uuid)->first();
        $first = $order->Items()->orderBy('created_at')->first();

        $response = $this->orderAction('item.consume', $order, [], ['item_uuid' => $first->uuid]);

        $response->assertOk();
        $this->assertEquals([true, false], array_column($response->json('data.orders.0.items'), 'consumed'));
        $this->assertEquals(1, $this->plate->fresh()->amount_in_use);
        $this->assertEquals(1, $this->plate->fresh()->amount_dirty);
    }

    public function test_only_served_portions_can_be_consumed(): void
    {
        $this->placeOrder([$this->item()]);
        $order = Order::where('table_uuid', $this->table->uuid)->first();

        $response = $this->orderAction('consume', $order);

        $response->assertBadRequest();
        $this->assertEquals('Nothing served is left to eat', $response->json('message'));
    }

    //| finishing & cancelling

    public function test_finishing_pays_the_order_with_its_tip_and_discount(): void
    {
        $order = $this->placeServedOrder();
        $this->orderAction('adjust', $order, ['tip' => 3, 'discount' => 1.5])->assertOk();

        $response = $this->orderAction('finish', $order);

        $response->assertOk();
        $this->assertEquals(Order::STATUS_FINISHED, $order->fresh()->status);
        $this->assertEquals(6.5, $this->restaurant->fresh()->shift_total);
        $this->assertEquals('12:06', $order->fresh()->paid_at->format('H:i'));
        $this->assertEquals([], $response->json('data.orders'));
        // The plate still on the table is cleared away
        $this->assertEquals(1, $this->plate->fresh()->amount_dirty);
    }

    public function test_a_discount_never_makes_the_table_get_money(): void
    {
        $order = $this->placeServedOrder();
        $this->orderAction('adjust', $order, ['tip' => 0, 'discount' => 50])->assertOk();

        $this->orderAction('finish', $order)->assertOk();

        $this->assertEquals(0.0, $this->restaurant->fresh()->shift_total);
    }

    public function test_tips_and_discounts_cannot_be_negative(): void
    {
        $order = $this->placeServedOrder();

        $this->orderAction('adjust', $order, ['tip' => -1, 'discount' => 0])->assertBadRequest();
        $this->orderAction('adjust', $order, ['tip' => 0, 'discount' => -1])->assertBadRequest();
    }

    public function test_orders_paid_when_placed_are_not_paid_twice(): void
    {
        // Orders placed before paying on finishing were added to the shift total right away
        $order = $this->placeServedOrder();
        $order->paid_at = $order->ordered_at;
        $order->save();
        $this->restaurant->shift_total = 5;
        $this->restaurant->save();

        $this->orderAction('finish', $order)->assertOk();

        $this->assertEquals(5.0, $this->restaurant->fresh()->shift_total);
    }

    public function test_cancelling_pays_nothing_and_clears_the_table(): void
    {
        $order = $this->placeServedOrder();

        $response = $this->orderAction('cancel', $order);

        $response->assertOk();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertEquals(0.0, $this->restaurant->fresh()->shift_total);
        $this->assertEquals(1, $this->plate->fresh()->amount_dirty);
        $this->assertEquals([], $response->json('data.orders'));
    }

    public function test_cancelling_an_order_paid_when_placed_takes_its_money_back(): void
    {
        $order = $this->placeServedOrder();
        $order->paid_at = $order->ordered_at;
        $order->save();
        $this->restaurant->shift_total = 5;
        $this->restaurant->save();

        $this->orderAction('cancel', $order)->assertOk();

        $this->assertEquals(0.0, $this->restaurant->fresh()->shift_total);
    }

    public function test_finished_orders_cannot_be_changed(): void
    {
        $order = $this->placeServedOrder();
        $this->orderAction('finish', $order)->assertOk();

        $response = $this->orderAction('cancel', $order);

        $response->assertBadRequest();
        $this->assertEquals('This order is already finished', $response->json('message'));
    }

    //| editing

    public function test_editing_adds_and_removes_unserved_items_and_changes_customers(): void
    {
        $this->placeOrder([$this->item($this->bilbo), $this->item($this->frodo)]);
        $order = Order::where('table_uuid', $this->table->uuid)->first();
        [$first, $second] = $order->Items()->orderBy('created_at')->get()->all();

        $response = $this->orderAction('edit', $order, ['items' => [
            ['uuid' => $first->uuid, 'customer_uuid' => $this->frodo->uuid],
            ['recipe_uuid' => $this->recipe->uuid, 'customer_uuid' => null],
            ['recipe_uuid' => $this->recipe->uuid, 'customer_uuid' => $this->bilbo->uuid],
        ]]);

        $response->assertOk();
        $this->assertNull($second->fresh());
        $this->assertEquals($this->frodo->uuid, $first->fresh()->customer_uuid);
        $this->assertEquals(3, $order->Items()->count());
        $this->assertEquals(15.0, $order->fresh()->total_price);
    }

    public function test_served_items_cannot_be_removed(): void
    {
        $order = $this->placeServedOrder();

        $response = $this->orderAction('edit', $order, ['items' => [
            ['recipe_uuid' => $this->recipe->uuid, 'customer_uuid' => null],
        ]]);

        $response->assertBadRequest();
        $this->assertEquals('Stew has already been served', $response->json('message'));
        $this->assertEquals(1, $order->Items()->count());
    }

    public function test_adding_to_a_fulfilled_order_opens_it_again(): void
    {
        $order = $this->placeServedOrder();
        $served = $order->Items()->first();

        $this->orderAction('edit', $order, ['items' => [
            ['uuid' => $served->uuid, 'customer_uuid' => null],
            ['recipe_uuid' => $this->recipe->uuid, 'customer_uuid' => null],
        ]])->assertOk();

        $this->assertEquals(Order::STATUS_OPEN, $order->fresh()->status);
        $this->assertNull($order->fresh()->fulfilled_at);
    }

    public function test_items_of_another_order_cannot_be_edited_in(): void
    {
        $this->placeOrder([$this->item()]);
        $this->placeOrder([$this->item()]);
        [$first, $second] = Order::where('table_uuid', $this->table->uuid)->orderBy('created_at')->get()->all();
        $foreign = $second->Items()->first();

        $response = $this->orderAction('edit', $first, ['items' => [['uuid' => $foreign->uuid, 'customer_uuid' => null]]]);

        $response->assertNotFound();
    }
}
