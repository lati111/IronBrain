<?php

namespace Tests\Unit\DataProvider\Datatable\Auth;

use App\Models\Auth\Role;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class RoleDatatableTest extends AbstractDatatableTester
{
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
}
