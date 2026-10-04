<?php

namespace Tests\Feature\Api\DnD\Restaurant\FinanceApi;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Transaction;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers the finances: the log of transactions, and the breakdown of a day's finances. Test recipes cost 5, and the
 * service starts at 09:00
 */
class FinancesTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $stew;
    private Recipe $pie;
    private Table $table;
    private Customer $alice;
    private Customer $bob;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->pie = $this->createTestRecipe($this->restaurant, 'Pie');
        $this->table = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $this->alice = $this->createTestCustomer($this->restaurant, $this->table, 'Alice', 1);
        $this->bob = $this->createTestCustomer($this->restaurant, $this->table, 'Bob', 2);
        $this->assignTestWaiter($this->table);

        app(ShiftService::class)->startService($this->restaurant, '09:00');
        $this->restaurant->refresh();
    }

    private function callApi(string $route, array $parameters = [], string $method = 'postJson', array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->$method(route($route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    /** Places an order, as recipe and customer pairs */
    private function order(array ...$items): Order
    {
        $this->callApi('api.dnd.restaurant.service.order.place', ['table_uuid' => $this->table->uuid, 'items' => array_map(
            fn (array $item) => ['recipe_uuid' => $item[0]->uuid, 'customer_uuid' => $item[1]?->uuid], $items,
        )])->assertCreated();

        return Order::where('restaurant_uuid', $this->restaurant->uuid)->latest('created_at')->latest('uuid')->first();
    }

    private function endService(): Transaction
    {
        $this->callApi('api.dnd.restaurant.service.time', ['advance' => 60])->assertOk();
        $this->callApi('api.dnd.restaurant.service.end')->assertOk();

        return Transaction::where('restaurant_uuid', $this->restaurant->uuid)->latest('occurred_at')->first();
    }

    private function getDetails(Transaction $transaction): TestResponse
    {
        return $this->callApi('api.dnd.restaurant.finances.transaction', [], 'getJson', ['transaction_uuid' => $transaction->uuid])->assertOk();
    }

    //| log

    public function test_ending_a_service_logs_its_profit(): void
    {
        $this->order([$this->stew, $this->alice], [$this->pie, $this->bob]);

        $transaction = $this->endService();

        $this->assertEquals([Transaction::TYPE_SERVICE, 'Daily finances', 10.0, '1492-03-01 10:00'], [
            $transaction->type, $transaction->title, $transaction->amount, $transaction->occurred_at->format('Y-m-d H:i'),
        ]);
    }

    public function test_transactions_are_listed_most_recent_first(): void
    {
        $first = $this->endService();
        // The staff went home when the first service ended
        app(ShiftService::class)->startService($this->restaurant->fresh(), '12:00');
        $this->assignTestWaiter($this->table, 'Second waiter');
        $this->order([$this->stew, null]);
        $second = $this->endService();

        $response = $this->callApi('api.dnd.restaurant.finances.get', [], 'getJson');

        $response->assertOk();
        $this->assertEquals([$second->uuid, $first->uuid], array_column($response->json('data.transactions'), 'uuid'));
        $this->assertEquals(5, $response->json('data.transactions.0.amount'));
    }

    public function test_another_restaurants_transaction_is_not_found(): void
    {
        $this->endService();
        $other = $this->createTestRestaurant($this->getAdminUser(), 'Other');
        $transaction = Transaction::where('restaurant_uuid', $this->restaurant->uuid)->first();

        $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->getJson(route('api.dnd.restaurant.finances.transaction', ['restaurant_uuid' => $other->uuid, 'transaction_uuid' => $transaction->uuid]))
            ->assertNotFound();
    }

    //| daily finances

    public function test_the_day_lists_each_order(): void
    {
        $first = $this->order([$this->stew, $this->alice], [$this->stew, $this->bob]);
        $this->callApi('api.dnd.restaurant.service.order.adjust', ['tip' => 2, 'discount' => 1], 'postJson', ['order_uuid' => $first->uuid])->assertOk();
        $cancelled = $this->order([$this->pie, $this->alice]);
        $this->callApi('api.dnd.restaurant.service.order.cancel', [], 'postJson', ['order_uuid' => $cancelled->uuid])->assertOk();

        $orders = $this->getDetails($this->endService())->json('data.orders');

        $this->assertCount(2, $orders);
        $this->assertEquals(['Booth', 'finished', 10, 1, 2, 11], [
            $orders[0]['table'], $orders[0]['status'], $orders[0]['total_price'], $orders[0]['discount'], $orders[0]['tip'], $orders[0]['paid'],
        ]);
        $this->assertEquals([['name' => 'Stew', 'price' => 5, 'customer' => 'Alice'], ['name' => 'Stew', 'price' => 5, 'customer' => 'Bob']], $orders[0]['items']);
        $this->assertEquals(['cancelled', 0], [$orders[1]['status'], $orders[1]['paid']]);
    }

    public function test_the_day_shows_the_profit_per_customer(): void
    {
        // Alice's 10 and Bob's 5 share the tip of 3 by what they ordered, while the table's own portion is unassigned
        $order = $this->order([$this->stew, $this->alice], [$this->pie, $this->alice], [$this->stew, $this->bob]);
        $this->callApi('api.dnd.restaurant.service.order.adjust', ['tip' => 3, 'discount' => 0], 'postJson', ['order_uuid' => $order->uuid])->assertOk();
        $this->order([$this->pie, null]);

        $customers = $this->getDetails($this->endService())->json('data.customers');

        $this->assertEquals([
            ['name' => 'Alice', 'portions' => 2, 'profit' => 12],
            ['name' => 'Bob', 'portions' => 1, 'profit' => 6],
            ['name' => 'Unassigned', 'portions' => 1, 'profit' => 5],
        ], $customers);
    }

    public function test_the_day_counts_the_recipes_sold(): void
    {
        $this->order([$this->stew, $this->alice], [$this->pie, $this->bob]);
        $this->order([$this->stew, $this->bob]);
        $cancelled = $this->order([$this->pie, $this->alice]);
        $this->callApi('api.dnd.restaurant.service.order.cancel', [], 'postJson', ['order_uuid' => $cancelled->uuid])->assertOk();

        $response = $this->getDetails($this->endService());

        $this->assertEquals([
            ['name' => 'Stew', 'amount' => 2, 'total' => 10],
            ['name' => 'Pie', 'amount' => 1, 'total' => 5],
        ], $response->json('data.recipes'));
        $this->assertEquals(['started_at' => '1492-03-01 09:00', 'ended_at' => '1492-03-01 10:00', 'profit' => 15], $response->json('data.service'));
    }

    //| added by hand

    private function addTransaction(array $parameters): TestResponse
    {
        return $this->callApi('api.dnd.restaurant.finances.create', array_merge(['title' => 'Sold the old oven', 'amount' => 1234], $parameters));
    }

    /** The restaurant's coins, from gold down to bits */
    private function getCoins(): array
    {
        $restaurant = $this->restaurant->fresh();
        return [$restaurant->gold, $restaurant->silver, $restaurant->copper, $restaurant->bits];
    }

    public function test_a_transaction_is_added_with_a_description(): void
    {
        $response = $this->addTransaction(['description' => "Bought by the smith.\nPaid in full."]);

        $response->assertCreated();
        $this->assertEquals(['Sold the old oven', 1234], [$response->json('data.transactions.0.title'), $response->json('data.transactions.0.amount')]);

        $details = $this->getDetails(Transaction::find($response->json('data.uuid')));
        $this->assertEquals([Transaction::TYPE_MANUAL, "Bought by the smith.\nPaid in full.", '1492-03-01 09:00'],
            [$details->json('data.type'), $details->json('data.description'), $details->json('data.occurred_at')]);
        $this->assertNull($details->json('data.orders'));
    }

    public function test_the_description_is_optional(): void
    {
        $response = $this->addTransaction(['description' => '  ']);

        $response->assertCreated();
        $this->assertNull(Transaction::find($response->json('data.uuid'))->description);
    }

    public function test_money_coming_in_is_added_to_the_restaurant(): void
    {
        $this->addTransaction(['amount' => 1234])->assertCreated();

        $this->assertEquals([1, 2, 3, 4], $this->getCoins());
    }

    public function test_money_going_out_is_taken_from_the_restaurant(): void
    {
        // A gold is broken up to pay 5 bits
        $this->restaurant->gold = 2;
        $this->restaurant->save();

        $this->addTransaction(['title' => 'Bought candles', 'amount' => -5])->assertCreated();

        $this->assertEquals([1, 9, 9, 5], $this->getCoins());
        $this->assertEquals(-5, Transaction::where('title', 'Bought candles')->value('amount'));
    }

    public function test_more_money_cant_go_out_than_there_is(): void
    {
        $this->restaurant->copper = 1;
        $this->restaurant->save();

        $response = $this->addTransaction(['amount' => -11]);

        $response->assertBadRequest();
        $this->assertEquals('Not enough money: 1cp 1b needed, 1cp available', $response->json('message'));
        $this->assertFalse(Transaction::where('type', Transaction::TYPE_MANUAL)->exists());
    }

    public function test_transactions_are_validated(): void
    {
        $this->addTransaction(['title' => ''])->assertBadRequest();
        $this->addTransaction(['amount' => 0])->assertBadRequest();
        $this->addTransaction(['amount' => 'a'])->assertBadRequest();
        $this->assertFalse(Transaction::where('type', Transaction::TYPE_MANUAL)->exists());
    }

    public function test_the_finances_page_is_shown(): void
    {
        $this->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.finances', ['restaurant_uuid' => $this->restaurant->uuid]))
            ->assertOk()
            ->assertSee('Finances');
    }
}
