<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServiceHistory;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers summarising and ending a service. The test recipe costs 5, and the service starts at 09:00
 */
class EndServiceTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private Recipe $stew;
    private Recipe $pie;
    private Table $table;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->pie = $this->createTestRecipe($this->restaurant, 'Pie');
        $this->table = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $this->assignTestWaiter($this->table);

        app(ShiftService::class)->startService($this->restaurant, '09:00');
        $this->restaurant->refresh();
    }

    private function callService(string $route, array $parameters = [], string $method = 'postJson', array $routeParameters = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($this->getAdminUser())
            ->$method(route('api.dnd.restaurant.service.' . $route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function order(Recipe ...$recipes): void
    {
        $items = array_map(fn (Recipe $recipe) => ['recipe_uuid' => $recipe->uuid, 'customer_uuid' => null], $recipes);
        $this->callService('order.place', ['table_uuid' => $this->table->uuid, 'items' => $items])->assertCreated();
    }

    public function test_the_summary_lists_what_was_sold(): void
    {
        $this->order($this->stew, $this->stew, $this->pie);
        $this->callService('time', ['advance' => 90])->assertOk();

        $response = $this->callService('summary', [], 'getJson');

        $response->assertOk();
        $this->assertEquals('09:00', $response->json('data.started_at'));
        $this->assertEquals('10:30', $response->json('data.time'));
        // The orders aren't finished yet, so nothing has been paid
        $this->assertEquals(0.0, $response->json('data.profit'));
        $this->assertEquals([
            ['name' => 'Pie', 'amount' => 1, 'total' => 5],
            ['name' => 'Stew', 'amount' => 2, 'total' => 10],
        ], $response->json('data.items'));
    }

    public function test_ending_logs_the_service_and_adds_the_profit(): void
    {
        $this->restaurant->gold = 1;
        $this->restaurant->save();
        $this->order($this->stew, $this->stew, $this->pie);
        $this->callService('time', ['advance' => 600])->assertOk();

        $response = $this->callService('end');

        $response->assertOk();
        $this->assertEquals(15.0, $response->json('data.profit'));

        $restaurant = $this->restaurant->fresh();
        $this->assertNull($restaurant->current_time);
        $this->assertEquals(0.0, $restaurant->shift_total);
        $this->assertEquals(1, $restaurant->gold);
        $this->assertEquals(1, $restaurant->copper);
        $this->assertEquals(5, $restaurant->bits);

        $service = ServiceHistory::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->assertEquals('1492-03-01 09:00', $service->started_at->format('Y-m-d H:i'));
        $this->assertEquals('1492-03-01 19:00', $service->ended_at->format('Y-m-d H:i'));
        $this->assertEquals(15.0, $service->profit);
    }

    public function test_ending_finishes_the_remaining_orders(): void
    {
        $this->order($this->stew);

        $this->callService('end')->assertOk();

        $this->assertEquals(Order::STATUS_FINISHED, Order::where('table_uuid', $this->table->uuid)->first()->status);
    }

    public function test_ending_cleans_all_equipment(): void
    {
        $plate = $this->createTestEquipment($this->restaurant, 1, 'Plate', false);
        $this->ownEquipment($this->restaurant, $plate, 10);
        $plate->amount_dirty = 4;
        $plate->amount_in_use = 2;
        $plate->save();

        $this->callService('end')->assertOk();

        $this->assertEquals(0, $plate->fresh()->amount_dirty);
        $this->assertEquals(2, $plate->fresh()->amount_in_use);
    }

    public function test_plates_of_unfinished_orders_are_cleaned_too(): void
    {
        $plate = $this->createTestEquipment($this->restaurant, 1, 'Plate', false);
        $this->ownEquipment($this->restaurant, $plate, 10);
        $this->addRecipeEquipment($this->stew, $plate, 1, \App\Enum\DnD\Restaurant\EquipmentStage::SERVING);
        $this->order($this->stew);

        $meal = $this->createTestMeal($this->restaurant, $this->stew, \App\Models\DnD\Restaurant\Meal::STATUS_COOKING);
        $meal->cooking_ends_at = $this->restaurant->getNow()->subMinutes(5);
        $meal->expires_at = $this->restaurant->getNow()->addHours(6);
        $meal->cooking_settled = true;
        $meal->equipment = [['equipment_uuid' => $plate->uuid, 'amount' => 1, 'stage' => 'serving', 'container' => false]];
        $meal->save();
        $this->callService('meal.plate', [], 'postJson', ['meal_uuid' => $meal->uuid])->assertOk();
        $servedMeal = \App\Models\DnD\Restaurant\ServedMeal::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->callService('plate.deliver', ['table_uuid' => $this->table->uuid], 'postJson', ['plate_uuid' => $servedMeal->uuid])->assertOk();
        $this->assertEquals(1, $plate->fresh()->amount_in_use);

        $this->callService('end')->assertOk();

        $this->assertEquals(0, $plate->fresh()->amount_in_use);
        $this->assertEquals(0, $plate->fresh()->amount_dirty);
    }

    public function test_a_service_can_only_end_once(): void
    {
        $this->callService('end')->assertOk();

        $response = $this->callService('end');

        $response->assertBadRequest();
        $this->assertEquals('No service is ongoing', $response->json('message'));
        $this->assertEquals(1, ServiceHistory::where('restaurant_uuid', $this->restaurant->uuid)->count());
    }

    public function test_a_service_started_before_logging_still_gets_logged(): void
    {
        ServiceHistory::where('restaurant_uuid', $this->restaurant->uuid)->delete();

        $this->callService('end')->assertOk();

        $service = ServiceHistory::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->assertEquals('08:00', $service->started_at->format('H:i'));
        $this->assertEquals('09:00', $service->ended_at->format('H:i'));
    }

    //| moving on to the next day

    public function test_ending_can_move_on_to_the_next_day(): void
    {
        $cellar = $this->createTestStorage($this->restaurant, 100, 0, 'Cellar');
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->storeIngredient($cellar, $potato, 2, 1);
        $this->storeIngredient($cellar, $potato, 3, 5);

        $response = $this->callService('end', ['advance_day' => 1]);

        $response->assertOk();
        $this->assertEquals('1492-03-02', $response->json('data.date'));
        $this->assertEquals([['name' => 'Potato', 'amount' => 2, 'location' => 'Cellar']], $response->json('data.expired'));
        $this->assertEquals(3, $this->getStoredIngredientAmount($cellar, $potato));
    }

    public function test_ending_keeps_the_day_unless_asked(): void
    {
        $response = $this->callService('end');

        $response->assertOk();
        $this->assertEquals('1492-03-01', $response->json('data.date'));
        $this->assertEquals([], $response->json('data.expired'));
    }
}
