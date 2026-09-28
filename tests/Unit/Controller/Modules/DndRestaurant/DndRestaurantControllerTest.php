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

        $this->permission = new Permission();
        $this->permission->permission = 'dnd_restaurant.access';
        $this->permission->name = 'Access DnD Restaurant Simulator';
        $this->permission->description = 'Allows the user to access the DnD restaurant simulator module.';
        $this->permission->group = 'dnd_restaurant';
        $this->permission->save();
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
