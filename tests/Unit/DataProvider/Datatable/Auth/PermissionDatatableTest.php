<?php

namespace Tests\Unit\DataProvider\Datatable\Auth;

use App\Models\Auth\Permission;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class PermissionDatatableTest extends AbstractDatatableTester
{
    public function testOverviewData(): void
    {
        $this->createRandomEntities(Permission::class, 50);
        $route = route('config.permission.overview.datatable');
        $datatable = $this->actingAs($this->getAdminUser())->get($route, array_merge($this->getDefaultFilters(), []));

        $datatable->assertStatus(200);
        $this->assertFiltersValid($datatable->json());
        $this->assertContentValid(Permission::class, $datatable->json(), [
            'permission',
            'name',
            'description',
            'group',
            null,
        ]);
    }
}
