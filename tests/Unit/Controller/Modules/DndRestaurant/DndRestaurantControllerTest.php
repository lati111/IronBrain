<?php

namespace Tests\Unit\Controller\Modules\DndRestaurant;

use App\Models\Auth\Permission;
use App\Models\Auth\RolePermission;
use App\Models\Auth\User;
use Tests\Unit\Controller\AbstractControllerUnitTester;

class DndRestaurantControllerTest extends AbstractControllerUnitTester
{
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
            ->get(route('dnd_restaurant.home.show'));
        $this->assertView($response, 'modules.dnd_restaurant.home');
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
            ->get(route('dnd_restaurant.home.show'));
        $this->assertView($response, 'modules.dnd_restaurant.home');
    }

    public function testOverviewShowWithoutPermission(): void
    {
        $response = $this
            ->actingAs($this->getTestUser())
            ->get(route('dnd_restaurant.home.show'));
        $response->assertViewIs('errors.auth.forbidden');
    }
}
