<?php

namespace Tests\Unit\DataProvider\Datatable\Auth;

use App\Models\Auth\User;
use Tests\Unit\DataProvider\Datatable\AbstractDatatableTester;

class UserDatatableTest extends AbstractDatatableTester
{
    public function testOverviewData(): void
    {
        $this->createRandomEntities(User::class, 50);
        $route = route('config.user.overview.datatable');
        $datatable = $this->actingAs($this->getAdminUser())->get($route, array_merge($this->getDefaultFilters(), []));

        $datatable->assertStatus(200);
        $this->assertFiltersValid($datatable->json());
        $this->assertContentValid(User::class, $datatable->json(), [
            null,
            'name',
            'email',
            null,
            null,
        ]);
    }
}
