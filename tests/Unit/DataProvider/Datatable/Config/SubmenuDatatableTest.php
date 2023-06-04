<?php

namespace Tests\Unit\DataProvider\Datatable\Config;

use App\Models\Config\Project;
use App\Models\Config\Submenu;
use Database\Seeders\NavSeeder;
use Database\Seeders\ProjectSeeder;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class SubmenuDatatableTest extends AbstractDatatableTester
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(ProjectSeeder::class);
        $this->seed(NavSeeder::class);
    }

    public function testOverviewData(): void
    {
        $project = $this->getRandomEntity(Project::class);
        $this->createRandomEntities(Submenu::class, 50, ['project_id' => $project->id]);
        $route = route('config.projects.submenu.overview.datatable', [$project->id]);
        $datatable = $this->actingAs($this->getAdminUser())->get($route, array_merge($this->getDefaultFilters(), []));

        $datatable->assertStatus(200);
        $this->assertFiltersValid($datatable->json());
        $this->assertContentValid(Submenu::class, $datatable->json(), [
            'name',
            'route',
            'order',
            null,
        ]);
    }
}
