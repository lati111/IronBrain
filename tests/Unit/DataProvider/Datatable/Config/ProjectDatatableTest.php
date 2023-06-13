<?php

namespace Tests\Unit\DataProvider\Datatable\Config;

use App\Models\Auth\User;
use App\Models\Config\Project;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class ProjectDatatableTest extends AbstractDatatableTester
{
    public function testOverviewData(): void
    {
        $this->createRandomEntities(Project::class, 50);
        $route = route('config.project.overview.datatable');
        $datatable = $this->actingAs($this->getAdminUser())->get($route, array_merge($this->getDefaultFilters(), []));

        $datatable->assertStatus(200);
        $this->assertFiltersValid($datatable->json());
        $this->assertContentValid(Project::class, $datatable->json(), [
            null,
            'name',
            'description',
            'route',
            'order',
            null,
        ]);
    }
}
