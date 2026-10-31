<?php

namespace Tests\Unit\Command;

use App\Models\Config\Module;
use App\Models\Config\Submodule;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\AbstractUnitTester;

class ImportModulesTest extends AbstractUnitTester
{
    public function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function module(string $code, array $overrides = []): array
    {
        return array_replace([
            'code' => $code,
            'name' => ucfirst($code),
            'description' => 'A module used for testing',
            'thumbnail' => null,
            'route' => 'home',
            'permission' => null,
            'order' => 1,
            'in_overview' => true,
            'in_nav' => true,
            'requires_login' => false,
            'submodules' => [],
        ], $overrides);
    }

    private function writeModules(array $modules): void
    {
        Storage::put('config/Core/modules.json', json_encode($modules));
    }

    public function testImportCreatesModules(): void
    {
        $this->writeModules([$this->module('import_test', ['submodules' => [
            ['code' => 'sub', 'name' => 'Sub', 'order' => 1, 'route' => 'home', 'permission' => null],
        ]])]);

        $this->artisan('import:modules')->assertExitCode(0);

        $module = Module::where('code', 'import_test')->first();
        $this->assertNotNull($module);
        $this->assertTrue(Submodule::where('module_id', $module->id)->where('code', 'sub')->exists());
    }

    public function testInvalidRouteFailsAndRollsBack(): void
    {
        $this->writeModules([
            $this->module('import_test'),
            $this->module('import_test_broken', ['route' => 'route.that.does.not.exist', 'order' => 2]),
        ]);

        $this->artisan('import:modules')->assertExitCode(1);

        // The valid module is rolled back along with the invalid one
        $this->assertFalse(Module::where('code', 'import_test')->exists());
    }

    public function testUnknownPermissionFails(): void
    {
        $this->writeModules([$this->module('import_test', ['permission' => 'permission.that.does.not.exist'])]);

        $this->artisan('import:modules')->assertExitCode(1);
        $this->assertFalse(Module::where('code', 'import_test')->exists());
    }

    public function testInvalidJsonFails(): void
    {
        Storage::put('config/Core/modules.json', '{not json');

        $this->artisan('import:modules')->assertExitCode(1);
    }

    public function testRemovedModulesAreRemovedAndRestored(): void
    {
        $this->writeModules([$this->module('import_test'), $this->module('import_test_removed', ['order' => 2])]);
        $this->artisan('import:modules')->assertExitCode(0);

        // A new module takes over the order of the removed module
        $this->writeModules([$this->module('import_test'), $this->module('import_test_new', ['order' => 2])]);
        $this->artisan('import:modules')->assertExitCode(0);
        $this->assertFalse(Module::where('code', 'import_test_removed')->exists());
        $this->assertTrue(Module::onlyTrashed()->where('code', 'import_test_removed')->exists());

        $this->writeModules([$this->module('import_test'), $this->module('import_test_removed', ['order' => 3])]);
        $this->artisan('import:modules')->assertExitCode(0);
        $this->assertTrue(Module::where('code', 'import_test_removed')->exists());
        $this->assertEquals(1, Module::withTrashed()->where('code', 'import_test_removed')->count());
    }

    public function testModulesCanSwapOrder(): void
    {
        $this->writeModules([$this->module('import_test_a', ['order' => 1]), $this->module('import_test_b', ['order' => 2])]);
        $this->artisan('import:modules')->assertExitCode(0);

        $this->writeModules([$this->module('import_test_a', ['order' => 2]), $this->module('import_test_b', ['order' => 1])]);
        $this->artisan('import:modules')->assertExitCode(0);

        $this->assertEquals(2, Module::where('code', 'import_test_a')->value('order'));
        $this->assertEquals(1, Module::where('code', 'import_test_b')->value('order'));
    }

    public function testDuplicateOrderFails(): void
    {
        $this->writeModules([$this->module('import_test_a'), $this->module('import_test_b')]);

        $this->artisan('import:modules')->assertExitCode(1);
    }

    public function testRemovedSubmodulesAreRemoved(): void
    {
        $submodule = ['code' => 'sub', 'name' => 'Sub', 'order' => 1, 'route' => 'home', 'permission' => null];
        $this->writeModules([$this->module('import_test', ['submodules' => [$submodule]])]);
        $this->artisan('import:modules')->assertExitCode(0);

        $this->writeModules([$this->module('import_test')]);
        $this->artisan('import:modules')->assertExitCode(0);

        $module = Module::where('code', 'import_test')->first();
        $this->assertFalse(Submodule::where('module_id', $module->id)->exists());
    }
}
