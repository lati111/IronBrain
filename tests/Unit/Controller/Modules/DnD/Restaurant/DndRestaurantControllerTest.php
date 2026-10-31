<?php

namespace Tests\Unit\Controller\Modules\DnD\Restaurant;

use App\Models\Auth\Permission;
use App\Models\Auth\RolePermission;
use App\Models\Auth\User;
use Tests\Traits\DnD\RestaurantTestHelper;
use Tests\Unit\Controller\AbstractControllerUnitTester;

class DndRestaurantControllerTest extends AbstractControllerUnitTester
{
    use RestaurantTestHelper;

    protected Permission $permission;

    public function setUp(): void
    {
        parent::setUp();

        // Imported from permissions.json during setup
        $this->permission = Permission::where('permission', 'dnd_restaurant.access')->firstOrFail();
    }

    private function getTestUser(): User
    {
        return User::where('email', 'test@test.nl')->first();
    }

    //| show overview test
    public function testOverviewShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.overview'));
        $this->assertView($response, 'modules.dnd.restaurant.home');
    }

    public function testOverviewShowWithPermission(): void
    {
        $user = $this->getTestUser();

        $rolePermission = new RolePermission();
        $rolePermission->role_id = $user->role_id;
        $rolePermission->permission_id = $this->permission->id;
        $rolePermission->save();

        $response = $this
            ->actingAs($user)
            ->get(route('dnd.restaurant.overview'));
        $this->assertView($response, 'modules.dnd.restaurant.home');
    }

    public function testOverviewShowWithoutPermission(): void
    {
        $response = $this
            ->actingAs($this->getTestUser())
            ->get(route('dnd.restaurant.overview'));
        $response->assertViewIs('errors.auth.forbidden');
    }

    //| show management test
    public function testManagementShowForOwner(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant->uuid]));
        $this->assertView($response, 'modules.dnd.restaurant.management');
    }

    public function testManagementShowForSharedUser(): void
    {
        $user = $this->getAdminUser();
        $owner = $this->getTestUser();
        $sharedRestaurant = $this->createTestRestaurant($owner, 'Shared');
        $this->grantRestaurantAccess($sharedRestaurant, $user);

        $response = $this
            ->actingAs($user)
            ->get(route('dnd.restaurant.management', ['restaurant_uuid' => $sharedRestaurant->uuid]));
        $this->assertView($response, 'modules.dnd.restaurant.management');
    }

    public function testManagementShowWithoutAccess(): void
    {
        $restaurant = $this->createTestRestaurant($this->getTestUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant->uuid]));
        $response->assertNotFound();
    }

    //| show manage test
    public function testManageShowForOwner(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.manage', ['restaurant_uuid' => $restaurant->uuid]));
        $this->assertView($response, 'modules.dnd.restaurant.manage');
    }

    public function testManageShowWithoutAccess(): void
    {
        $restaurant = $this->createTestRestaurant($this->getTestUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.manage', ['restaurant_uuid' => $restaurant->uuid]));
        $response->assertNotFound();
    }

    //| show service test
    public function testServiceShowKeepsTheCurrentTime(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());
        $restaurant->current_time = '15:30';
        $restaurant->save();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.service', ['restaurant_uuid' => $restaurant->uuid]));
        $this->assertView($response, 'modules.dnd.restaurant.service');

        $this->assertEquals('15:30:00', $restaurant->fresh()->current_time);
    }

    public function testServiceShowWithoutServiceGoesToManagement(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.service', ['restaurant_uuid' => $restaurant->uuid]));
        $response->assertRedirect(route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant->uuid]));
    }

    public function testServiceStartDefaultsToOpening(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());
        $restaurant->shift_total = 42.5;
        $restaurant->save();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post(route('dnd.restaurant.service.start', ['restaurant_uuid' => $restaurant->uuid]));
        $response->assertRedirect(route('dnd.restaurant.service', ['restaurant_uuid' => $restaurant->uuid]));

        $this->assertEquals('08:00:00', $restaurant->fresh()->current_time);
        $this->assertEquals(0.0, $restaurant->fresh()->shift_total);
        $this->assertEquals('08:00', $restaurant->fresh()->getCurrentService()->started_at->format('H:i'));
    }

    public function testServiceStartAtAGivenTime(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());

        $this
            ->actingAs($this->getAdminUser())
            ->post(route('dnd.restaurant.service.start', ['restaurant_uuid' => $restaurant->uuid]), ['time' => '10:15'])
            ->assertRedirect(route('dnd.restaurant.service', ['restaurant_uuid' => $restaurant->uuid]));

        $this->assertEquals('10:15:00', $restaurant->fresh()->current_time);
        $this->assertEquals('10:15', $restaurant->fresh()->getCurrentService()->started_at->format('H:i'));
    }

    public function testServiceStartPicksUpAnOngoingService(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());
        $restaurant->current_time = '15:30';
        $restaurant->shift_total = 42.5;
        $restaurant->save();

        $this
            ->actingAs($this->getAdminUser())
            ->post(route('dnd.restaurant.service.start', ['restaurant_uuid' => $restaurant->uuid]), ['time' => '08:00'])
            ->assertRedirect(route('dnd.restaurant.service', ['restaurant_uuid' => $restaurant->uuid]));

        $this->assertEquals('15:30:00', $restaurant->fresh()->current_time);
        $this->assertEquals(42.5, $restaurant->fresh()->shift_total);
    }

    public function testServiceStartWithoutAccess(): void
    {
        $restaurant = $this->createTestRestaurant($this->getTestUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post(route('dnd.restaurant.service.start', ['restaurant_uuid' => $restaurant->uuid]));
        $response->assertNotFound();
        $this->assertNull($restaurant->fresh()->current_time);
    }

    public function testManagementShowNonExistent(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.management', ['restaurant_uuid' => $this->faker->uuid()]));
        $response->assertNotFound();
    }
}
