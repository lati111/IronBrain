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
    public function testServiceShowSetsCurrentTimeToOpening(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());
        $restaurant->current_time = '15:30';
        $restaurant->save();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.service', ['restaurant_uuid' => $restaurant->uuid]));
        $this->assertView($response, 'modules.dnd.restaurant.service');

        $this->assertEquals('08:00:00', $restaurant->fresh()->current_time);
    }

    public function testServiceShowWithoutAccess(): void
    {
        $restaurant = $this->createTestRestaurant($this->getTestUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.service', ['restaurant_uuid' => $restaurant->uuid]));
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
