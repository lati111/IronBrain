<?php

namespace Tests\Unit\DataProvider\Datatable\Auth;

use App\Enum\Auth\RoleEnum;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class RoleDatatableTest extends AbstractDatatableTester
{
    //| overview
    public function testOverviewData(): void
    {
        $this->createRandomEntities(Role::class, 50);
        $route = route('config.role.overview.datatable');
        $datatable = $this->actingAs($this->getAdminUser())->get($route, array_merge($this->getDefaultFilters(), []));

        $datatable->assertStatus(200);
        $this->assertFiltersValid($datatable->json());
        $this->assertContentValid(Role::class, $datatable->json(), [
            'name',
            'description',
            null,
        ]);
    }

    //| toggle data list
    public function testListToggleableData(): void
    {
        $role = $this->createRandomEntity(Role::class);
        $this->createRandomEntities(Permission::class, 50);
        $route = route('config.role.permission.datatable', [$role->id]);
        $datatable = $this->actingAs($this->getAdminUser())->get($route, array_merge($this->getDefaultFilters(), []));

        $datatable->assertStatus(200);
        $this->assertFiltersValid($datatable->json());
        $this->assertContentValid(Permission::class, $datatable->json(), [
            null,
            'name',
            'description',
            null,
        ]);
    }

    public function testListToggleableDataRoleNotFound(): void
    {
        $role_id = $this->getFalseId(Role::class);
        $route = route('config.role.permission.datatable', [$role_id]);
        $response = $this->actingAs($this->getAdminUser())->get($route, array_merge($this->getDefaultFilters(), []));

        $response->assertStatus(404);
        $this->assertEquals(RoleEnum::ROLE_NOT_FOUND_MESSAGE, $response->json());
    }
}
