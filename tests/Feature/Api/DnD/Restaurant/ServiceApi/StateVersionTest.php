<?php

namespace Tests\Feature\Api\DnD\Restaurant\ServiceApi;

use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;
use Tests\Traits\DnD\RestaurantTestHelper;

/**
 * Covers the version a restaurant keeps of the changes made to it, which lets the pages refreshing during service
 * learn that nothing changed without fetching everything again
 */
class StateVersionTest extends AbstractFeatureTester
{
    use RestaurantTestHelper;

    private Restaurant $restaurant;

    public function setUp(): void
    {
        parent::setUp();
        $this->restaurant = $this->createTestRestaurant($this->getAdminUser());
        app(ShiftService::class)->startService($this->restaurant, '12:00');
    }

    private function fetch(string $route, ?int $version = null): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->getJson(route($route, ['restaurant_uuid' => $this->restaurant->uuid]) . ($version !== null ? '?version=' . $version : ''))
            ->assertOk();
    }

    private function advance(): TestResponse
    {
        return $this->withHeaders(['Accept' => 'application/json'])->actingAs($this->getAdminUser())
            ->postJson(route('api.dnd.restaurant.service.time', ['restaurant_uuid' => $this->restaurant->uuid]), ['advance' => 5])
            ->assertOk();
    }

    public function test_the_service_is_unchanged_until_something_changes(): void
    {
        $version = $this->fetch('api.dnd.restaurant.service.get')->json('data.version');

        $unchanged = $this->fetch('api.dnd.restaurant.service.get', $version);
        $this->assertEquals(['unchanged' => true, 'version' => $version], $unchanged->json('data'));

        // A change returns the version it brought, so the page that made it doesn't fetch it again
        $changed = $this->advance()->json('data.version');
        $this->assertGreaterThan($version, $changed);
        $this->assertNotNull($this->fetch('api.dnd.restaurant.service.get', $version)->json('data.clock'));
        $this->assertTrue($this->fetch('api.dnd.restaurant.service.get', $changed)->json('data.unchanged'));
    }

    public function test_the_floor_is_unchanged_until_something_changes(): void
    {
        $version = $this->fetch('api.dnd.restaurant.floor.get')->json('data.version');

        $this->assertTrue($this->fetch('api.dnd.restaurant.floor.get', $version)->json('data.unchanged'));

        $this->advance();
        $this->assertNotNull($this->fetch('api.dnd.restaurant.floor.get', $version)->json('data.tables'));
    }

    public function test_looking_changes_nothing(): void
    {
        $before = $this->restaurant->fresh()->state_version;

        $this->fetch('api.dnd.restaurant.service.get');
        $this->fetch('api.dnd.restaurant.floor.get');
        $this->fetch('api.dnd.restaurant.inventory.get');

        $this->assertEquals($before, $this->restaurant->fresh()->state_version);
    }
}
