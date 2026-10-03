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

    //| show inventory test
    public function testInventoryShowForOwner(): void
    {
        $restaurant = $this->createTestRestaurant($this->getAdminUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.inventory', ['restaurant_uuid' => $restaurant->uuid]));
        $this->assertView($response, 'modules.dnd.restaurant.inventory');
    }

    public function testInventoryShowForSharedUser(): void
    {
        $user = $this->getAdminUser();
        $owner = $this->getTestUser();
        $sharedRestaurant = $this->createTestRestaurant($owner, 'Shared');
        $this->grantRestaurantAccess($sharedRestaurant, $user);

        $response = $this
            ->actingAs($user)
            ->get(route('dnd.restaurant.inventory', ['restaurant_uuid' => $sharedRestaurant->uuid]));
        $this->assertView($response, 'modules.dnd.restaurant.inventory');
    }

    public function testInventoryShowWithoutAccess(): void
    {
        $restaurant = $this->createTestRestaurant($this->getTestUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.inventory', ['restaurant_uuid' => $restaurant->uuid]));
        $response->assertNotFound();
    }

    public function testInventoryShowNonExistent(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.restaurant.inventory', ['restaurant_uuid' => $this->faker->uuid()]));
        $response->assertNotFound();
    }
}
