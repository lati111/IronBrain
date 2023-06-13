<?php

namespace Tests\Unit\Controller\Config;

use App\Enum\Auth\PermissionEnum;
use App\Models\Auth\Permission;

use Tests\Unit\Controller\AbstractControllerUnitTester;

class PermissionControllerTest extends AbstractControllerUnitTester
{
    //| show overview test
    public function testOverviewShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.permission.overview'));
        $this->assertView($response, 'config.permission.overview');
    }

    //| show new permission test
    public function testNewPermissionShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.permission.new'));
        $this->assertView($response, 'config.permission.modify');
    }

    //| show modify permission test
    public function testModifyPermissionShow(): void
    {
        $permission = $this->getRandomEntity(Permission::class);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.permission.modify', [$permission->id]));
        $this->assertView($response, 'config.permission.modify');
    }

    public function testModifyPermissionShowInvalidPermission(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.permission.modify', [$this->getFalseId(Permission::class)]));
        $this->assertRedirect($response, 'config.permission.overview');
        $this->assertEquals(PermissionEnum::NOT_FOUND_MESSAGE, session('error'));
    }

    //| save permission test
    public function testSaveNewPermissionValid(): void
    {
        $route = route('config.permission.save');
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $group = $this->faker->regexify('[A-Za-z0-9]{48}');
        $description = $this->faker->regexify('[A-Za-z0-9]{48}');
        $permissionString = $this->getNewPermissionString();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'permission' =>  $permissionString,
                'name' =>  $name,
                'group' =>  $group,
                'description' =>  $description,
            ]);
        $this->assertRedirect($response, 'config.permission.overview', [
            "message" => PermissionEnum::SAVED_MESSAGE,
        ]);

        $permission = Permission::where('permission', $permissionString)->first();
        $this->assertNotNull($permission);
        $this->assertEquals($name, $permission->name);
        $this->assertEquals($permissionString, $permission->permission);
        $this->assertEquals($group, $permission->group);
        $this->assertEquals($description, $permission->description);
    }

    public function testSaveModifiedPermissionValid(): void
    {
        $route = route('config.permission.save');
        $permissionString = $this->getNewPermissionString();

        $id = $this->getRandomEntity(Permission::class)->id;
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $group = $this->faker->regexify('[A-Za-z0-9]{48}');
        $description = $this->faker->regexify('[A-Za-z0-9]{48}');

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' => $id,
                'permission' =>  $permissionString,
                'name' =>  $name,
                'group' =>  $group,
                'description' =>  $description,
            ]);
        $this->assertRedirect($response, 'config.permission.overview', [
            "message" => PermissionEnum::SAVED_MESSAGE,
        ]);

        $permission = Permission::where('permission', $permissionString)->first();
        $this->assertNotNull($permission);
        $this->assertEquals($id, $permission->id);
        $this->assertEquals($name, $permission->name);
        $this->assertEquals($name, $permission->name);
        $this->assertEquals($permissionString, $permission->permission);
        $this->assertEquals($group, $permission->group);
        $this->assertEquals($description, $permission->description);
    }

    public function testSavePermissionIdValidation(): void
    {
        $route = route('config.permission.save');
        $permission = $this->getRandomEntity(Permission::class);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' =>  $permission->id,
            ]);
        $this->assertValidationValid('id');

        //does not exist
        $this->post($route, [
            'id' => $this->getFalseId(Permission::class)
        ]);
        $this->assertValidationExists('id');

        //is nullable
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationValid('id');

        //is integer
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' => "test",
            ]);
        $this->assertValidationInteger('id');
    }

    public function testSavePermissionPermissionValidation(): void
    {
        $route = route('config.permission.save');
        $permissionString = $this->getNewPermissionString();

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'permission' =>  $permissionString,
            ]);
        $this->assertValidationValid('permission');

        //required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('permission');

        //too long
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'permission' => $this->faker->regexify('[A-Za-z0-9]{130}'),
            ]);
        $this->assertValidationTooLong('permission', 128);

        //is string
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'permission' => 44,
            ]);
        $this->assertValidationString('permission');
    }

    public function testSavePermissionNameValidation(): void
    {
        $route = route('config.permission.save');

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $this->faker->regexify('[A-Za-z0-9]{26}'),
            ]);
        $this->assertValidationValid('name');

        //required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('name');

        //too long
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' => $this->faker->regexify('[A-Za-z0-9]{50}'),
            ]);
        $this->assertValidationTooLong('name', 48);

        //is string
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' => 44,
            ]);
        $this->assertValidationString('name');
    }

    public function testSavePermissionGroupValidation(): void
    {
        $route = route('config.permission.save');

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'group' =>  $this->faker->regexify('[A-Za-z0-9]{48}'),
            ]);
        $this->assertValidationValid('group');

        //required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('group');

        //too long
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'group' => $this->faker->regexify('[A-Za-z0-9]{65}'),
            ]);
        $this->assertValidationTooLong('group', 64);

        //is string
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'group' => 44,
            ]);
        $this->assertValidationString('group');
    }

    public function testSavePermissionDescriptionValidation(): void
    {
        $route = route('config.permission.save');

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'description' =>  $this->faker->regexify('[A-Za-z0-9]{48}'),
            ]);
        $this->assertValidationValid('description');

        //required
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationRequired('description');

        //is string
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'description' => 44,
            ]);
        $this->assertValidationString('description');
    }

    public function testSavePermissionNotUnique(): void
    {
        $route = route('config.permission.save');
        $permission = $this->getRandomEntity(Permission::class);
        $other_permission = Permission::where('permission', '!=', $permission->permission)->first();

        //new
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'permission' =>  $permission->permission,
                'name' =>  $this->faker->regexify('[A-Za-z0-9]{26}'),
                'group' =>  $this->faker->regexify('[A-Za-z0-9]{48}'),
                'description' =>  $this->faker->regexify('[A-Za-z0-9]{48}'),
            ]);
        $this->assertEquals(PermissionEnum::NOT_UNIQUE_MESSAGE, session('error'));

        //modify
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' => $other_permission->id,
                'permission' =>  $permission->permission,
                'name' =>  $this->faker->regexify('[A-Za-z0-9]{26}'),
                'group' =>  $this->faker->regexify('[A-Za-z0-9]{48}'),
                'description' =>  $this->faker->regexify('[A-Za-z0-9]{48}'),
            ]);
        $this->assertEquals(PermissionEnum::NOT_UNIQUE_MESSAGE, session('error'));
    }

    //| delete permission test
    public function testDeletePermissionValid(): void
    {
        $permission = $this->createRandomEntity(Permission::class);
        $route = route('config.permission.delete', $permission->id);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertNull(Permission::where('id', $permission->id)->first());
        $this->assertRedirect($response, 'config.permission.overview', [
            "message" => PermissionEnum::DELETED_MESSAGE,
        ]);
    }

    public function testDeletePermissionNotFound(): void
    {
        $route = route('config.permission.delete', $this->getFalseId(Permission::class));

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertRedirect($response, 'config.permission.overview', [
            "error" => PermissionEnum::NOT_FOUND_MESSAGE,
        ]);
    }

    //| getter
    private function getNewPermissionString() {
        $permission = null;
        while ($permission === null) {
            $perm = $this->faker->regexify('[A-Za-z0-9]{48}');
            if (Permission::where('permission', $perm)->count() === 0) {
                $permission = $perm;
            }
        }

        return $permission;
    }
}
