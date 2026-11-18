<?php

namespace Tests\Unit\Command;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\RolePermission;
use App\Models\Config\Module;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\AbstractUnitTester;

class ImportPermissionsTest extends AbstractUnitTester
{
    public function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function writePermissions(array $permissions): void
    {
        Storage::put('config/Core/permissions.json', json_encode(['import_test' => array_map(fn (string $permission) => [
            'permission' => $permission,
            'name' => $permission,
            'description' => 'A permission used for testing',
        ], $permissions)]));
    }

    public function testImportCreatesPermissions(): void
    {
        $this->writePermissions(['import.test']);

        $this->artisan('import:permissions')->assertExitCode(0);

        $this->assertEquals('import_test', Permission::where('permission', 'import.test')->value('group'));
    }

    public function testInvalidJsonFails(): void
    {
        Storage::put('config/Core/permissions.json', json_encode(['import_test' => [['name' => 'Missing permission code']]]));

        $this->artisan('import:permissions')->assertExitCode(1);
    }

    public function testRemovedPermissionsAreDeletedWithTheirGrants(): void
    {
        $this->writePermissions(['import.test', 'import.test.removed']);
        $this->artisan('import:permissions')->assertExitCode(0);

        $permission = Permission::where('permission', 'import.test.removed')->first();
        $grant = new RolePermission();
        $grant->role_id = Role::first()->id;
        $grant->permission_id = $permission->id;
        $grant->save();

        $this->writePermissions(['import.test']);
        $this->artisan('import:permissions')->assertExitCode(0);

        $this->assertFalse(Permission::where('permission', 'import.test.removed')->exists());
        $this->assertFalse(RolePermission::where('permission_id', $permission->id)->exists());
    }

    public function testRemovedPermissionStillUsedByModuleIsKept(): void
    {
        $this->writePermissions(['import.test', 'import.test.module']);
        $this->artisan('import:permissions')->assertExitCode(0);

        $module = new Module();
        $module->code = 'import_test';
        $module->name = 'Import Test';
        $module->description = 'A module used for testing';
        $module->order = 1;
        $module->in_overview = false;
        $module->in_nav = false;
        $module->permission_id = Permission::where('permission', 'import.test.module')->value('id');
        $module->save();

        $this->writePermissions(['import.test']);
        $this->artisan('import:permissions')->assertExitCode(0);

        $this->assertTrue(Permission::where('permission', 'import.test.module')->exists());
    }
}
