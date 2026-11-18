<?php

namespace Tests\Feature\Api\DnD\Restaurant\PlayerApi;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use App\Service\DnD\Restaurant\PresenceService;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers an invited player opening a running service: the page and the state it loads
 */
class PlayerJoinServiceTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;
    private User $player;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        $this->player = $this->getRandomUser(['dnd_restaurant.access']);
        $this->grantRestaurantAccess($this->restaurant, $this->player, RestaurantUser::ROLE_PLAYER);

        $mine = $this->createTestEmployee($this->restaurant, null, 'Mine');
        $mine->player_uuid = $this->player->uuid;
        $mine->station = WorkStation::COOKING;
        $mine->save();
        $table = $this->createTestTable($this->restaurant, null, 4, 'Booth');
        $this->assignTestWaiter($table, 'Sam');
        $this->createTestCustomer($this->restaurant, $table, 'Bilbo');
        $this->createTestCustomer($this->restaurant, null, 'Frodo');
        $this->createTestCooker($this->restaurant);

        app(ShiftService::class)->startService($this->restaurant, '09:00');
        $this->restaurant->refresh();
    }

    public function test_players_open_the_service_page(): void
    {
        $this->actingAs($this->player)
            ->get(route('dnd.restaurant.service', ['restaurant_uuid' => $this->restaurant->uuid]))
            ->assertOk();
    }

    private function getService(User $user, array $query = []): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($user)
            ->getJson(route('api.dnd.restaurant.service.get', ['restaurant_uuid' => $this->restaurant->uuid] + $query));
    }

    public function test_players_with_employees_are_online_while_their_page_asks_for_the_service(): void
    {
        $this->assertEquals([], $this->getService($this->getAdminUser())->json('data.online'));

        $this->getService($this->player)->assertOk();

        $this->assertEquals([$this->player->uuid], $this->getService($this->getAdminUser())->json('data.online'));
    }

    public function test_online_is_sent_along_even_when_nothing_changed(): void
    {
        $version = $this->getService($this->player)->json('data.version');

        $response = $this->getService($this->getAdminUser(), ['version' => $version]);

        $this->assertTrue($response->json('data.unchanged'));
        $this->assertEquals([$this->player->uuid], $response->json('data.online'));
    }

    public function test_players_are_offline_once_their_page_stops_asking(): void
    {
        $this->getService($this->player)->assertOk();

        $this->travel(PresenceService::ONLINE_SECONDS + 1)->seconds();

        $this->assertEquals([], $this->getService($this->getAdminUser())->json('data.online'));
    }

    public function test_players_load_the_service_and_floor(): void
    {
        foreach (['api.dnd.restaurant.service.get', 'api.dnd.restaurant.floor.get'] as $route) {
            $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->player)
                ->getJson(route($route, ['restaurant_uuid' => $this->restaurant->uuid]))
                ->assertOk();
        }
    }
}
