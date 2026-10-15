<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Transaction;
use App\Service\DnD\Restaurant\OrderService;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers how satisfied customers are with what they are served. A portion starts at 3 stars, with a star more when it
 * turned out 5 or more over the recipe's difficulty of 10 and less when below it, a star more when it arrived within
 * 20 minutes of ordering and less when it took over 40, and two less when it had spoiled. Taking the order takes the
 * waiter 4 minutes and bringing a plate 2, so a portion plated straight away arrives 6 minutes after ordering
 */
class SatisfactionTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $recipe;
    private Table $table;
    private Customer $bilbo;
    private Customer $frodo;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        app(ShiftService::class)->startService($this->restaurant, '12:00');
        $this->restaurant->refresh();

        $this->recipe = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->recipe->difficulty = 10;
        $this->recipe->save();
        $this->table = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $this->bilbo = $this->createTestCustomer($this->restaurant, $this->table, 'Bilbo', 1);
        $this->frodo = $this->createTestCustomer($this->restaurant, $this->table, 'Frodo', 2);
        $this->assignTestWaiter($this->table);
    }

    private function callService(string $route, array $parameters = [], array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function advance(int $minutes): void
    {
        $this->callService('time', ['advance' => $minutes])->assertOk();
    }

    /** Places an order of a portion for each customer given, and lets the waiter take it */
    private function order(Customer ...$customers): void
    {
        $items = array_map(fn (Customer $customer) => ['recipe_uuid' => $this->recipe->uuid, 'customer_uuid' => $customer->uuid], $customers);
        $this->callService('order.place', ['table_uuid' => $this->table->uuid, 'items' => $items])->assertCreated();
        $this->advance(OrderService::ORDER_MINUTES_PER_CUSTOMER * 2);
    }

    /** Plates a portion of a freshly cooked meal that turned out at a quality */
    private function plate(?int $quality): ServedMeal
    {
        $meal = $this->createTestMeal($this->restaurant, $this->recipe, Meal::STATUS_COOKING);
        $meal->portions_left = 1;
        $meal->quality = $quality;
        $meal->cooking_ends_at = $this->restaurant->fresh()->getNow();
        $meal->expires_at = $this->restaurant->fresh()->getNow()->addHours(6);
        $meal->cooking_settled = true;
        $meal->equipment = [];
        $meal->save();

        $this->callService('meal.plate', [], ['meal_uuid' => $meal->uuid])->assertOk();
        return ServedMeal::where('restaurant_uuid', $this->restaurant->uuid)->whereNull('order_item_uuid')->latest()->first();
    }

    /** Lets the waiter bring a plate, returning the rating of the portion it served */
    private function deliver(ServedMeal $plate): ?int
    {
        $this->callService('plate.deliver', ['table_uuid' => $this->table->uuid], ['plate_uuid' => $plate->uuid])->assertOk();
        $this->advance(OrderService::DELIVERY_MINUTES);

        return OrderItem::where('uuid', $plate->fresh()->order_item_uuid)->value('satisfaction');
    }

    //| rating

    public function test_a_good_meal_served_quickly_delights(): void
    {
        $this->order($this->bilbo);

        $this->assertEquals(5, $this->deliver($this->plate(15)));
    }

    public function test_quality_counts_against_the_recipes_difficulty(): void
    {
        $this->order($this->bilbo, $this->frodo);

        // Without a quality only the quick service counts, and just below the difficulty undoes it
        $this->assertEquals(4, $this->deliver($this->plate(null)));
        $this->assertEquals(3, $this->deliver($this->plate(8)));
    }

    public function test_long_waits_disappoint(): void
    {
        $this->order($this->bilbo);
        $this->advance(60);

        // Ordered 66 minutes before it arrived, and turned out exactly at the difficulty
        $this->assertEquals(1, $this->deliver($this->plate(10)));
    }

    public function test_spoiled_portions_disappoint(): void
    {
        $this->order($this->bilbo);
        $plate = $this->plate(10);
        // The plate stays fresh for the recipe's 30 minutes, and arrives 37 minutes after ordering
        $this->advance(31);

        $this->assertEquals(1, $this->deliver($plate));
    }

    //| averages

    public function test_orders_and_customers_show_how_satisfied_they_were(): void
    {
        $this->order($this->bilbo, $this->frodo);
        $this->deliver($this->plate(15));
        $this->deliver($this->plate(8));

        $state = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->get(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));
        $this->assertEquals(4, $state->json('data.orders.0.satisfaction'));
        $this->assertEqualsCanonicalizing([5, 3], array_column($state->json('data.orders.0.items'), 'satisfaction'));

        $this->callService('end')->assertOk();
        $transaction = Transaction::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $details = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->get(route('api.dnd.restaurant.finances.transaction', ['restaurant_uuid' => $this->restaurant->uuid, 'transaction_uuid' => $transaction->uuid]));

        $this->assertEquals(4, $details->json('data.service.satisfaction'));
        $this->assertEquals(4, $details->json('data.orders.0.satisfaction'));
        // Both portions were ordered at once, so either customer may have been served first
        $customers = collect($details->json('data.customers'));
        $this->assertEqualsCanonicalizing(['Bilbo', 'Frodo'], $customers->pluck('name')->all());
        $this->assertEqualsCanonicalizing([5, 3], $customers->pluck('satisfaction')->all());
    }

    public function test_nothing_is_rated_before_it_is_served(): void
    {
        $this->order($this->bilbo);

        $state = $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->get(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid]));

        $this->assertNull($state->json('data.orders.0.satisfaction'));
        $this->assertNull($state->json('data.orders.0.items.0.satisfaction'));
    }
}
