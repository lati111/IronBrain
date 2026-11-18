<?php

namespace Tests\Unit\Controller\Modules\DnD\Timeline;

use App\Models\Auth\Permission;
use App\Models\Auth\RolePermission;
use App\Models\Auth\User;
use App\Models\DnD\Timeline\Timeline;
use Tests\Traits\DnD\TimelineTestHelper;
use Tests\Unit\Controller\AbstractControllerUnitTester;

class DndTimelineControllerTest extends AbstractControllerUnitTester
{
    use TimelineTestHelper;

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

    //| show timeline test
    public function testTimelineShowForOwner(): void
    {
        $timeline = $this->createTestTimeline($this->getAdminUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.timeline.show', ['timeline_uuid' => $timeline->uuid]));
        $this->assertView($response, 'modules.dnd.timeline.timeline');
        $response->assertViewHas('timeline', fn (Timeline $shown) => $shown->uuid === $timeline->uuid);
    }

    public function testTimelineShowOfOtherUserIsNotFound(): void
    {
        $timeline = $this->createTestTimeline($this->getTestUser());

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.timeline.show', ['timeline_uuid' => $timeline->uuid]));
        $response->assertNotFound();
    }

    public function testTimelineShowOfMissingTimelineIsNotFound(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('dnd.timeline.show', ['timeline_uuid' => $this->getFalseUuid(Timeline::class)]));
        $response->assertNotFound();
    }
}
