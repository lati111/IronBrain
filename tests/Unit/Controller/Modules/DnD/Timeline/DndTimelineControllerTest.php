<?php

namespace Tests\Unit\Controller\Modules\DnD\Timeline;

use App\Models\Auth\Permission;
use App\Models\Auth\RolePermission;
use App\Models\Auth\User;
use Tests\Unit\Controller\AbstractControllerUnitTester;

class DndTimelineControllerTest extends AbstractControllerUnitTester
{
    protected Permission $permission;

    public function setUp(): void
    {
        parent::setUp();

        // Imported from permissions.json during setup
        $this->permission = Permission::where('permission', 'dnd_timeline.access')->firstOrFail();
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
            ->get(route('dnd.timeline.overview'));
        $this->assertView($response, 'modules.dnd.timeline.home');
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
            ->get(route('dnd.timeline.overview'));
        $this->assertView($response, 'modules.dnd.timeline.home');
    }

    public function testOverviewShowWithoutPermission(): void
    {
        $response = $this
            ->actingAs($this->getTestUser())
            ->get(route('dnd.timeline.overview'));
        $response->assertViewIs('errors.auth.forbidden');
    }
}
