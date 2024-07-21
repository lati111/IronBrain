<?php

namespace Tests\Unit\DataProvider\Datatable\Config;

use App\Models\Auth\User;
use App\Models\Config\Module;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class ProjectDatatableTest extends AbstractDatatableTester
{
    public function testOverviewData(): void
    {
        $this->createRandomEntities(Module::class, 50);
        $route = route('config.project.overview.datatable');
        $datatable = $this->actingAs($this->getAdminUser())->get($route, array_merge($this->getDefaultFilters(), []));

        $datatable->assertStatus(200);
        $this->assertFiltersValid($datatable->json());
        $this->assertContentValid(Module::class, $datatable->json(), [
            null,
            'name',
            'description',
            'route',
            'order',
            null,
        ]);
    }
}
