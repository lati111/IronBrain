<?php

namespace Tests\Feature\Api\DnD\Restaurant\PlayerApi;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use App\Models\DnD\Restaurant\Storage;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers players: invited to a restaurant, controlling the employees assigned to them during service, and moving
 * ingredients around, while everything else stays with the owner and managers
 */
class PlayersTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private User $player;
    private Employee $mine;
    private Employee $theirs;
    private Table $table;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->player = $this->getRandomUser(['dnd_restaurant.access']);
        $this->grantRestaurantAccess($this->restaurant, $this->player, RestaurantUser::ROLE_PLAYER);

        $this->mine = $this->createTestEmployee($this->restaurant, null, 'Mine');
        $this->mine->player_uuid = $this->player->uuid;
        $this->mine->save();
        $this->theirs = $this->createTestEmployee($this->restaurant, null, 'Theirs');
        $this->table = $this->createTestTable($this->restaurant, null, 4, 'Booth');

        app(ShiftService::class)->startService($this->restaurant, '09:00');
        $this->restaurant->refresh();
    }

    /** Calls an API route of the restaurant, as the player unless someone else is given */
    private function callApi(string $route, array $parameters = [], string $method = 'postJson', array $routeParameters = [], ?User $user = null): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])
            ->actingAs($user ?? $this->player)
            ->$method(route($route, array_merge(['restaurant_uuid' => $this->restaurant->uuid], $routeParameters)), $parameters);
    }

    private function asOwner(string $route, array $parameters = [], string $method = 'postJson', array $routeParameters = []): TestResponse
    {
        return $this->callApi($route, $parameters, $method, $routeParameters, $this->getAdminUser());
    }

    //| inviting

    public function test_players_are_invited_by_username(): void
    {
        $access = RestaurantUser::where('user_uuid', $this->player->uuid)->first();
        $access->delete();

        $response = $this->asOwner('api.dnd.restaurant.floor.player.invite', ['username' => $this->player->username]);

        $response->assertCreated();
        $this->assertEquals(RestaurantUser::ROLE_PLAYER, RestaurantUser::where('user_uuid', $this->player->uuid)->value('role'));
        $this->assertEquals([['uuid' => $this->player->uuid, 'username' => $this->player->username]], $response->json('data.floor.players'));
    }

    public function test_only_users_without_access_can_be_invited(): void
    {
        $this->asOwner('api.dnd.restaurant.floor.player.invite', ['username' => $this->player->username])->assertBadRequest();
        $this->asOwner('api.dnd.restaurant.floor.player.invite', ['username' => $this->getAdminUser()->username])->assertBadRequest();
        $this->asOwner('api.dnd.restaurant.floor.player.invite', ['username' => 'nobody at all'])->assertNotFound();
    }

    public function test_removing_a_player_frees_their_employees(): void
    {
        $this->asOwner('api.dnd.restaurant.floor.player.remove', [], 'postJson', ['user_uuid' => $this->player->uuid])->assertOk();

        $this->assertFalse($this->restaurant->hasAccess($this->player));
        $this->assertNull($this->mine->fresh()->player_uuid);
    }

    public function test_employees_are_assigned_to_players(): void
    {
        $route = 'api.dnd.restaurant.floor.employee.edit';

        $this->asOwner($route, ['player_uuid' => $this->player->uuid], 'postJson', ['employee_uuid' => $this->theirs->uuid])->assertOk();
        $this->assertEquals($this->player->uuid, $this->theirs->fresh()->player_uuid);

        // Leaving the player out keeps them, while an empty one takes the employee off them
        $this->asOwner($route, ['name' => 'Renamed'], 'postJson', ['employee_uuid' => $this->theirs->uuid])->assertOk();
        $this->assertEquals($this->player->uuid, $this->theirs->fresh()->player_uuid);
        $this->asOwner($route, ['player_uuid' => ''], 'postJson', ['employee_uuid' => $this->theirs->uuid])->assertOk();
        $this->assertNull($this->theirs->fresh()->player_uuid);

        // Only players can be given employees
        $this->asOwner($route, ['player_uuid' => $this->getAdminUser()->uuid], 'postJson', ['employee_uuid' => $this->theirs->uuid])->assertNotFound();
    }

    //| what players can't do

    public function test_players_cant_run_the_restaurant(): void
    {
        $this->callApi('api.dnd.restaurant.service.time', ['advance' => 5])->assertForbidden();
        $this->callApi('api.dnd.restaurant.service.end')->assertForbidden();
        $this->callApi('api.dnd.restaurant.service.meal.plan', ['recipe_uuid' => 'x'])->assertForbidden();
        $this->callApi('api.dnd.restaurant.service.order.place', ['table_uuid' => $this->table->uuid])->assertForbidden();
        $this->callApi('api.dnd.restaurant.service.ingredient.add', ['amount' => 1], 'postJson', ['ingredient_uuid' => 'x'])->assertForbidden();
        $this->callApi('api.dnd.restaurant.inventory.ingredient.create', ['name' => 'Salt', 'expires_in' => 0, 'size' => 1])->assertForbidden();
        $this->callApi('api.dnd.restaurant.floor.table.create', ['name' => 'Bar', 'capacity' => 2])->assertForbidden();
        $this->callApi('api.dnd.restaurant.floor.player.invite', ['username' => 'someone'])->assertForbidden();
        $this->callApi('api.dnd.restaurant.finances.get', [], 'getJson')->assertForbidden();
        $this->callApi('api.dnd.restaurant.edit', ['name' => 'Mine now'])->assertForbidden();

        $this->assertEquals('09:00', $this->restaurant->fresh()->getNow()->format('H:i'));
    }

    public function test_players_only_see_the_inventory_and_service_pages(): void
    {
        $this->actingAs($this->player)->get(route('dnd.restaurant.management', ['restaurant_uuid' => $this->restaurant->uuid]))
            ->assertOk()->assertDontSee('Manage restaurant');
        $this->actingAs($this->player)->get(route('dnd.restaurant.service', ['restaurant_uuid' => $this->restaurant->uuid]))
            ->assertOk()->assertDontSee('End service');

        $this->actingAs($this->player)->get(route('dnd.restaurant.manage', ['restaurant_uuid' => $this->restaurant->uuid]))->assertForbidden();
        $this->actingAs($this->player)->get(route('dnd.restaurant.finances', ['restaurant_uuid' => $this->restaurant->uuid]))->assertForbidden();
        $this->actingAs($this->player)->post(route('dnd.restaurant.service.start', ['restaurant_uuid' => $this->restaurant->uuid]))->assertForbidden();
    }

    public function test_managers_keep_full_control(): void
    {
        $manager = User::factory()->create();
        $this->grantRestaurantAccess($this->restaurant, $manager);

        $this->callApi('api.dnd.restaurant.service.time', ['advance' => 5], 'postJson', [], $manager)->assertOk();
    }

    //| controlling employees

    public function test_the_service_tells_the_player_who_they_are(): void
    {
        $response = $this->callApi('api.dnd.restaurant.service.get', [], 'getJson')->assertOk();

        $this->assertEquals(['user_uuid' => $this->player->uuid, 'player' => true], $response->json('data.viewer'));
        $this->assertEquals($this->player->username, collect($response->json('data.employees'))->firstWhere('name', 'Mine')['player_name']);
        $this->assertFalse($this->asOwner('api.dnd.restaurant.service.get', [], 'getJson')->json('data.viewer.player'));
    }

    public function test_players_put_their_own_employees_to_work(): void
    {
        $route = 'api.dnd.restaurant.service.employee.assign';

        $this->callApi($route, ['station' => 'prep'], 'postJson', ['employee_uuid' => $this->mine->uuid])->assertOk();
        $this->assertEquals(WorkStation::PREP, $this->mine->fresh()->station);

        $response = $this->callApi($route, ['station' => 'prep'], 'postJson', ['employee_uuid' => $this->theirs->uuid]);
        $response->assertForbidden();
        $this->assertEquals('Theirs isn\'t one of your employees', $response->json('message'));
        $this->assertNull($this->theirs->fresh()->station);
    }

    public function test_players_have_their_own_employees_wait_tables(): void
    {
        $route = 'api.dnd.restaurant.service.table.waiter';
        $parameters = ['table_uuid' => $this->table->uuid];

        $this->callApi($route, ['employee_uuid' => $this->theirs->uuid], 'postJson', $parameters)->assertForbidden();

        $this->callApi($route, ['employee_uuid' => $this->mine->uuid], 'postJson', $parameters)->assertOk();
        $this->assertEquals($this->mine->uuid, $this->table->fresh()->employee_uuid);

        $this->callApi($route, [], 'postJson', $parameters)->assertOk();
        $this->assertNull($this->table->fresh()->employee_uuid);
    }

    public function test_players_cant_take_a_table_from_someone_elses_waiter(): void
    {
        $route = 'api.dnd.restaurant.service.table.waiter';
        $parameters = ['table_uuid' => $this->table->uuid];
        $this->asOwner($route, ['employee_uuid' => $this->theirs->uuid], 'postJson', $parameters)->assertOk();

        $this->callApi($route, ['employee_uuid' => $this->mine->uuid], 'postJson', $parameters)->assertForbidden();
        $this->callApi($route, [], 'postJson', $parameters)->assertForbidden();

        $this->assertEquals($this->theirs->uuid, $this->table->fresh()->employee_uuid);
    }

    //| moving ingredients

    private function setUpStorage(): array
    {
        $cellar = $this->createTestStorage($this->restaurant, 100, 0, 'Cellar');
        $pantry = $this->createTestStorage($this->restaurant, 100, 1, 'Pantry');
        $potato = $this->createTestIngredient($this->restaurant, 1, 'Potato');
        $this->storeIngredient($cellar, $potato, 5);

        return [$cellar, $pantry, $potato];
    }

    private function transfer(Ingredient $potato, ?Storage $from, ?Storage $to, string $type = 'ingredient', ?string $uuid = null): TestResponse
    {
        return $this->callApi('api.dnd.restaurant.inventory.transfer', array_filter([
            'type' => $type,
            'item_uuid' => $uuid ?? $potato->uuid,
            'amount' => 2,
            'from_storage_uuid' => $from?->uuid,
            'to_storage_uuid' => $to?->uuid,
        ]));
    }

    public function test_players_move_ingredients_between_storage_spaces(): void
    {
        [$cellar, $pantry, $potato] = $this->setUpStorage();

        $this->callApi('api.dnd.restaurant.inventory.get', [], 'getJson')->assertOk();
        $this->transfer($potato, $cellar, $pantry)->assertOk();

        $this->assertEquals([3, 2], [$this->getStoredIngredientAmount($cellar, $potato), $this->getStoredIngredientAmount($pantry, $potato)]);
    }

    public function test_players_neither_add_nor_remove_items(): void
    {
        [$cellar, $pantry, $potato] = $this->setUpStorage();
        $pot = $this->createTestEquipment($this->restaurant, 1, 'Pot');
        $this->storeEquipment($cellar, $pot, 2);

        $this->transfer($potato, null, $cellar)->assertForbidden();
        $this->transfer($potato, $cellar, null)->assertForbidden();
        $this->transfer($potato, $cellar, $pantry, 'equipment', $pot->uuid)->assertForbidden();

        $this->assertEquals(5, $this->getStoredIngredientAmount($cellar, $potato));
    }
}
