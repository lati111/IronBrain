<?php

namespace Tests\Unit\Command;

use App\Models\Config\Module;
use App\Models\Config\Submodule;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\AbstractUnitTester;

class UpdateModulesTest extends AbstractUnitTester
{
    public function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        Storage::put('config/Core/modules.json', json_encode([
            [
                'code' => 'update_test', 'name' => 'UpdateTest', 'description' => 'A module', 'thumbnail' => null, 'route' => 'home',
                'permission' => null, 'order' => 92, 'in_overview' => true, 'in_nav' => true,
                'submodules' => [
                    ['code' => 'old', 'name' => 'OldSub', 'order' => 1, 'route' => 'home', 'permission' => null],
                    ['code' => 'new', 'name' => 'NewSub', 'order' => 2, 'route' => 'home', 'permission' => null],
                ],
            ],
            [
                'code' => 'update_untouched', 'name' => 'Untouched', 'description' => 'A module', 'thumbnail' => null, 'route' => 'home',
                'permission' => null, 'order' => 93, 'in_overview' => true, 'in_nav' => true, 'submodules' => [],
            ],
        ]));
        $this->artisan('import:modules')->assertExitCode(0);

        Storage::put('config/Core/changelog.json', json_encode([
            ['version' => '1.0.0', 'date' => '2026-01-01', 'modules' => [
                'UpdateTest' => ['OldSub' => ['Old change'], 'NewSub' => ['First change']],
            ]],
            ['version' => '1.1.0', 'date' => '2026-02-01', 'modules' => [
                'UpdateTest' => ['NewSub' => ['permission' => 'admin', 'changes' => ['Newer change']]],
            ]],
        ]));
    }

    public function testModulesGetTheDateOfTheirLatestChange(): void
    {
        $this->artisan('modules:update')->assertExitCode(0);

        $module = Module::where('code', 'update_test')->first();
        $this->assertEquals('2026-02-01', $module->updated_at->toDateString());
        $this->assertEquals('2026-01-01', Submodule::where('module_id', $module->id)->where('code', 'old')->first()->updated_at->toDateString());
        $this->assertEquals('2026-02-01', Submodule::where('module_id', $module->id)->where('code', 'new')->first()->updated_at->toDateString());
    }

    public function testModulesWithoutChangesAreLeftAlone(): void
    {
        $before = Module::where('code', 'update_untouched')->first()->updated_at;

        $this->artisan('modules:update')->assertExitCode(0);

        $this->assertEquals($before, Module::where('code', 'update_untouched')->first()->updated_at);
    }
}
