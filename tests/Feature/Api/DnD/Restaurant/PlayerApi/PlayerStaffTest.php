<?php

namespace Tests\Feature\Api\DnD\Restaurant\PlayerApi;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Work;
use App\Service\DnD\Restaurant\ShiftService;
use App\Service\DnD\Restaurant\WorkService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers what players do during service through the employees assigned to them: what their employees' work area
 * lets them do in the kitchen, and what waiting a table lets them do there. The service starts at 09:00
 */
class PlayerStaffTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private User $player;
    private Employee $mine;
    private Table $table;
    private Recipe $stew;
    private Equipment $stove;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->player = $this->getRandomUser(['dnd_restaurant.access']);
        $this->grantRestaurantAccess($this->restaurant, $this->player, RestaurantUser::ROLE_PLAYER);

        $this->mine = $this->createTestEmployee($this->restaurant, null, 'Mine');
        $this->mine->player_uuid = $this->player->uuid;
        $this->mine->save();
        $this->table = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $this->stew = $this->createTestRecipe($this->restaurant, 'Stew');
        $this->stove = $this->createTestCooker($this->restaurant);

        app(ShiftService::class)->startService($this->restaurant, '09:00');
        $this->restaurant->refresh();
    }

    private function callApi(string $route, array $parameters = [], array $routeParameters = [], ?User $user = null): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($user ?? $this->player)
            ->postJson(route($route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    /** Puts the player's employee to work in an area, or has them wait the test table */
    private function assign(?WorkStation $station): void
    {
        $this->mine->station = $station;
        $this->mine->save();
        if ($station === null) {
            $this->table->employee_uuid = $this->mine->uuid;
            $this->table->save();
        }
    }

    private function advance(int $minutes): void
    {
        $this->callApi('api.dnd.restaurant.service.time', ['advance' => $minutes], user: $this->getAdminUser())->assertOk();
    }

    //| prep

    public function test_players_plan_and_prepare_meals_from_the_prep_area(): void
    {
        $this->callApi('api.dnd.restaurant.service.meal.plan', ['recipe_uuid' => $this->stew->uuid])->assertForbidden();

        $this->assign(WorkStation::PREP);
        $this->callApi('api.dnd.restaurant.service.meal.plan', ['recipe_uuid' => $this->stew->uuid])->assertCreated();
        $meal = Meal::where('restaurant_uuid', $this->restaurant->uuid)->first();
        $this->callApi('api.dnd.restaurant.service.meal.prepare', [], ['meal_uuid' => $meal->uuid])->assertOk();

        $this->assertTrue(Work::where('meal_uuid', $meal->uuid)->exists());
    }

    public function test_the_message_says_where_an_employee_has_to_work(): void
    {
        $response = $this->callApi('api.dnd.restaurant.service.meal.plan', ['recipe_uuid' => $this->stew->uuid]);

        $this->assertEquals('One of your employees has to work in prep for that', $response->json('message'));
    }

    //| cooking

    public function test_players_cook_and_serve_meals_from_the_cooking_area(): void
    {
        $meal = $this->createTestMeal($this->restaurant, $this->stew, Meal::STATUS_PREPPED);
        $this->callApi('api.dnd.restaurant.service.meal.cook', ['cooker_uuid' => $this->stove->uuid, 'slot' => 1], ['meal_uuid' => $meal->uuid])->assertForbidden();

        $this->assign(WorkStation::COOKING);
        $this->callApi('api.dnd.restaurant.service.meal.cook', ['cooker_uuid' => $this->stove->uuid, 'slot' => 1], ['meal_uuid' => $meal->uuid])->assertOk();
        $this->advance(20);
        $this->callApi('api.dnd.restaurant.service.meal.serve', [], ['meal_uuid' => $meal->uuid])->assertOk();
        $this->callApi('api.dnd.restaurant.service.meal.plate', [], ['meal_uuid' => $meal->uuid])->assertOk();

        $this->assertEquals(Meal::STATUS_SERVING, Meal::find($meal->uuid)?->status ?? Meal::STATUS_SERVING);
    }

    //| cleaning

    public function test_players_mark_equipment_to_clean_from_the_cleaning_area(): void
    {
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot', false);
        $this->ownEquipment($this->restaurant, $pot, 2);
        $pot->amount_dirty = 2;
        $pot->save();
        $this->callApi('api.dnd.restaurant.service.equipment.clean', ['amount' => 1], ['equipment_uuid' => $pot->uuid])->assertForbidden();

        $this->assign(WorkStation::CLEANING);
        $this->callApi('api.dnd.restaurant.service.equipment.clean', ['amount' => 1], ['equipment_uuid' => $pot->uuid])->assertOk();

        $this->assertTrue(Work::where('equipment_uuid', $pot->uuid)->exists());
    }

    //| waiting

    public function test_players_seat_customers_at_tables_they_wait(): void
    {
        $customer = $this->createTestCustomer($this->restaurant, null, 'Bilbo');
        $this->callApi('api.dnd.restaurant.floor.customer.seat', ['table_uuid' => $this->table->uuid, 'seat' => 1], ['customer_uuid' => $customer->uuid])->assertForbidden();

        $this->assign(null);
        $response = $this->callApi('api.dnd.restaurant.floor.customer.seat', ['table_uuid' => $this->table->uuid, 'seat' => 1], ['customer_uuid' => $customer->uuid]);
        $response->assertOk();
        $this->assertEquals('Mine is seating Bilbo', $response->json('message'));

        // The waiter walks them over, keeping the seat for them in the meantime
        $this->assertNull($customer->fresh()->table_uuid);
        $this->assertEquals(['table_uuid' => $this->table->uuid, 'seat' => 1],
            collect($response->json('data.floor.customers'))->firstWhere('uuid', $customer->uuid)['seating']);
        $this->advance(WorkService::SEATING_MINUTES);

        $this->assertEquals([$this->table->uuid, 1], [$customer->fresh()->table_uuid, $customer->fresh()->seat]);
    }

    public function test_players_dont_unseat_or_move_seated_customers(): void
    {
        $this->assign(null);
        $customer = $this->createTestCustomer($this->restaurant, $this->table, 'Bilbo');

        $this->callApi('api.dnd.restaurant.floor.customer.seat', [], ['customer_uuid' => $customer->uuid])->assertForbidden();
        $this->callApi('api.dnd.restaurant.floor.customer.seat', ['table_uuid' => $this->table->uuid, 'seat' => 2], ['customer_uuid' => $customer->uuid])->assertForbidden();
    }

    public function test_players_send_their_waiter_to_take_an_order_and_note_it_down(): void
    {
        $this->createTestCustomer($this->restaurant, $this->table, 'Bilbo');
        $this->callApi('api.dnd.restaurant.service.order.place', ['table_uuid' => $this->table->uuid])->assertForbidden();

        $this->assign(null);
        $this->callApi('api.dnd.restaurant.service.order.place', ['table_uuid' => $this->table->uuid])->assertCreated();
        $this->assertEquals(Order::STATUS_TAKING, Order::where('table_uuid', $this->table->uuid)->value('status'));

        $this->advance(2);
        $this->callApi('api.dnd.restaurant.service.order.place', ['table_uuid' => $this->table->uuid,
            'items' => [['recipe_uuid' => $this->stew->uuid, 'customer_uuid' => null]]])->assertCreated();
    }

    public function test_players_dont_note_down_orders_at_tables_they_dont_wait(): void
    {
        $this->createTestCustomer($this->restaurant, $this->table, 'Bilbo');

        $this->callApi('api.dnd.restaurant.service.order.place', ['table_uuid' => $this->table->uuid,
            'items' => [['recipe_uuid' => $this->stew->uuid, 'customer_uuid' => null]]])->assertForbidden();
    }

    //| conversions

    public function test_players_queue_conversions_during_service(): void
    {
        $storage = $this->createTestStorage($this->restaurant);
        $flour = $this->createTestIngredient($this->restaurant, 1, 'Wheat');
        $this->storeIngredient($storage, $flour, 5);
        $bread = $this->createTestIngredient($this->restaurant, 1, 'Flour');
        $conversion = new \App\Models\DnD\Restaurant\Conversion();
        $conversion->restaurant_uuid = $this->restaurant->uuid;
        $conversion->input_uuid = $flour->uuid;
        $conversion->output_uuid = $bread->uuid;
        $conversion->output_amount = 1;
        $conversion->minutes = 1;
        $conversion->save();

        $this->callApi('api.dnd.restaurant.service.conversion.queue', ['amount' => 1], ['conversion_uuid' => $conversion->uuid])->assertOk();
    }
}
