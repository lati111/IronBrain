<?php

namespace Tests\Unit\DataProvider\SelectorList\Auth;

use App\Models\Auth\Permission;
use Tests\Unit\DataProvider\SelectorList\AbstractSelectorListTester;

class PermissionSelectorListTest extends AbstractSelectorListTester
{
    public function testPermissionSelectorList(): void {
        $this->createRandomEntities(Permission::class, 25);
        $route = route('config.permission.selector.list');
        $selectorlist = $this->actingAs($this->getAdminUser())->get($route);

        $selectorlist->assertStatus(200);
        $this->assertContentValid(Permission::class, $selectorlist->json(), [
            'id',
            'name',
        ], true);
    }
}
