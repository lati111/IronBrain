<?php

namespace Tests\Unit\DataProvider\Datatable\Config;

use App\Models\Config\Module;
use App\Models\Config\Submodule;
use Database\Seeders\NavSeeder;
use Database\Seeders\ModuleSeeder;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class SubmenuDatatableTest extends AbstractDatatableTester
{
    public function setUp(): void
    {
        parent::setUp();
        $this->seed(ModuleSeeder::class);
        $this->seed(NavSeeder::class);
    }

    public function testOverviewData(): void
    {
        $project = $this->getRandomEntity(Module::class);
        $this->createRandomEntities(Submodule::class, 50, ['project_id' => $project->id]);
        $route = route('config.projects.submenu.overview.datatable', [$project->id]);
        $datatable = $this->actingAs($this->getAdminUser())->get($route, array_merge($this->getDefaultFilters(), []));

        $datatable->assertStatus(200);
        $this->assertFiltersValid($datatable->json());
        $this->assertContentValid(Submodule::class, $datatable->json(), [
            'name',
            'route',
            'order',
            null,
        ]);
    }
}
