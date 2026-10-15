<?php

namespace Tests\Feature\Api\DnD\Restaurant\PlayerApi;

use App\Http\Middleware\RestaurantManagerGuard;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * A safety net for what players of a restaurant may do. Every route of a restaurant is closed to its players, apart
 * from those listed here. A new route is closed until it is opened on purpose, and opening one means adding it here
 */
class PlayerRouteAccessTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    /** @var string The middleware that keeps players out */
    private const string GUARD = 'dnd.restaurant.manager';

    /** @var string[] The routes players may use, which check what the player may do with them themselves */
    private const array PLAYER_ROUTES = [
        // Pages
        'dnd.restaurant.management',
        'dnd.restaurant.service',
        // Looking at the service and the floor
        'api.dnd.restaurant.service.get',
        'api.dnd.restaurant.floor.get',
        // Moving their own employees
        'api.dnd.restaurant.service.employee.assign',
        'api.dnd.restaurant.service.table.waiter',
        // Moving ingredients between storage spaces
        'api.dnd.restaurant.inventory.get',
        'api.dnd.restaurant.inventory.transfer',
    ];

    /**
     * Gets every route of a restaurant, by name
     * @return array<string, RoutingRoute> The routes
     */
    private function getRestaurantRoutes(): array
    {
        $routes = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (in_array('restaurant_uuid', $route->parameterNames(), true) && $route->getName() !== null) {
                $routes[$route->getName()] = $route;
            }
        }

        ksort($routes);
        return $routes;
    }

    private function isGuarded(RoutingRoute $route): bool
    {
        return in_array(self::GUARD, $route->gatherMiddleware(), true)
            && !in_array(self::GUARD, $route->excludedMiddleware(), true);
    }

    public function test_only_the_listed_routes_are_open_to_players(): void
    {
        $routes = $this->getRestaurantRoutes();
        $this->assertNotEmpty($routes);

        $open = array_keys(array_filter($routes, fn (RoutingRoute $route) => !$this->isGuarded($route)));
        sort($open);
        $expected = self::PLAYER_ROUTES;
        sort($expected);

        $this->assertEquals($expected, $open, 'The routes open to players changed. If that was meant, update PLAYER_ROUTES');
    }

    public function test_players_are_turned_away_from_every_other_route(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());
        // With access to the module, so it is the restaurant's guard turning them away
        $player = $this->getRandomUser(['dnd_restaurant.access']);
        $this->grantRestaurantAccess($restaurant, $player, RestaurantUser::ROLE_PLAYER);
        $guarded = array_filter($this->getRestaurantRoutes(), fn (RoutingRoute $route) => $this->isGuarded($route));

        foreach ($guarded as $name => $route) {
            // Whatever else the route takes doesn't matter, as players are turned away before it is looked at
            $parameters = ['restaurant_uuid' => $restaurant->uuid];
            foreach ($route->parameterNames() as $parameter) {
                $parameters[$parameter] ??= (string) Str::uuid();
            }

            $method = strtolower(collect($route->methods())->first(fn (string $method) => $method !== 'HEAD'));
            $response = $this->actingAs($player)->json($method, route($name, $parameters));

            $this->assertEquals(403, $response->status(), sprintf('%s let a player through', $name));
            $this->assertEquals(RestaurantManagerGuard::MESSAGE, $response->json('message'), sprintf('%s turned a player away for another reason', $name));
        }

        $this->assertNotNull(Restaurant::find($restaurant->uuid), 'Nothing a player tried should have deleted the restaurant');
    }
}
