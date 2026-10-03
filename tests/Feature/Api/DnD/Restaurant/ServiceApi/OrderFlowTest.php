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
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers an order from being placed at a table, through plating and bringing its portions, to the meal being eaten.
 * The test recipe costs 5
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

    private function placeOrder(array $items, ?Table $table = null): TestResponse
    {
        return $this->callService('order.place', ['table_uuid' => ($table ?? $this->table)->uuid, 'items' => $items]);
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

    private function deliver(ServedMeal $plate, ?Table $table = null): TestResponse
    {
        return $this->callService('plate.deliver', ['table_uuid' => ($table ?? $this->table)->uuid], ['plate_uuid' => $plate->uuid]);
    }

    //| placing orders

    public function test_each_item_is_an_entry_and_adds_to_the_shift_total(): void
    {
        // The same recipe can be ordered several times, each for one customer
        $response = $this->placeOrder([$this->item($this->bilbo), $this->item($this->bilbo), $this->item($this->frodo), $this->item()]);

        $response->assertCreated();
        $order = Order::where('table_uuid', $this->table->uuid)->first();
        $this->assertEquals(4, $order->Items()->count());
        $this->assertEquals(2, $order->Items()->where('customer_uuid', $this->bilbo->uuid)->count());
        $this->assertEquals(1, $order->Items()->whereNull('customer_uuid')->count());
        $this->assertEquals(20.0, $order->total_price);
        $this->assertEquals('12:00', $order->ordered_at->format('H:i'));
        $this->assertEquals(20.0, $this->restaurant->fresh()->shift_total);
        $this->assertEquals(20.0, $response->json('data.clock.shift_total'));
        $this->assertEquals('Stew', $response->json('data.orders.0.items.0.name'));
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

    public function test_an_order_needs_something_ordered(): void
    {
        $this->placeOrder([])->assertBadRequest();
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
        $this->placeOrder([$this->item($this->bilbo), $this->item($this->frodo)]);
        $meal = $this->createCookedMeal(2);

        $this->deliver($this->plateMeal($meal))->assertOk();
        $order = Order::where('table_uuid', $this->table->uuid)->first();
        $this->assertEquals(Order::STATUS_OPEN, $order->status);
        $this->assertEquals(1, $order->Items()->whereNotNull('served_at')->count());

        $this->restaurant->refresh();
        $this->restaurant->setNow($this->restaurant->getNow()->addMinutes(15));
        $this->restaurant->save();
        $response = $this->deliver($this->plateMeal($meal));

        $response->assertOk();
        $this->assertEquals(Order::STATUS_FULFILLED, $order->fresh()->status);
        $this->assertEquals('12:15', $order->fresh()->fulfilled_at->format('H:i'));
        $this->assertEquals(15, $response->json('data.orders.0.ordered_minutes_ago'));
        $this->assertEquals(0, $response->json('data.orders.0.fulfilled_minutes_ago'));
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

    public function test_consuming_returns_serving_equipment_dirty_and_finishes_the_order(): void
    {
        $this->placeOrder([$this->item()]);
        $plate = $this->plateMeal($this->createCookedMeal(1));
        $this->deliver($plate)->assertOk();
        $order = Order::where('table_uuid', $this->table->uuid)->first();

        $response = $this->callService('order.consume', [], ['order_uuid' => $order->uuid]);

        $response->assertOk();
        $this->assertEquals(Order::STATUS_FINISHED, $order->fresh()->status);
        $this->assertNull($plate->fresh());
        $this->assertEquals(0, $this->plate->fresh()->amount_in_use);
        $this->assertEquals(1, $this->plate->fresh()->amount_dirty);
        $this->assertEquals([], $response->json('data.orders'));
    }

    public function test_only_fully_served_orders_can_be_consumed(): void
    {
        $this->placeOrder([$this->item()]);
        $order = Order::where('table_uuid', $this->table->uuid)->first();

        $response = $this->callService('order.consume', [], ['order_uuid' => $order->uuid]);

        $response->assertBadRequest();
        $this->assertEquals('Only fully served orders can be consumed', $response->json('message'));
    }
}
