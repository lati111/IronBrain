<?php

namespace Tests\Unit\Controller\Config;

use App\Enum\Auth\PermissionEnum;
use App\Enum\Auth\RoleEnum;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\RolePermission;
use App\Models\Auth\User;
use Tests\Unit\Controller\AbstractControllerUnitTester;

class RoleControllerTest extends AbstractControllerUnitTester
{
    //| show overview test
    public function testOverviewShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.role.overview'));
        $this->assertView($response, 'config.role.overview');
    }

    //| show new role test
    public function testNewRoleShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.role.new'));
        $this->assertView($response, 'config.role.modify');
    }

    //| show modify role tests
    public function testModifyRoleShow(): void
    {
        $role = $this->getRandomEntity(Role::class);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.role.modify', [$role->id]));
        $this->assertView($response, 'config.role.modify', [
            'role' => $role,
        ]);
    }

    public function testModifyRoleShowRoleNotFound(): void
    {
        $role_id = $this->getFalseId(Role::class);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.role.modify', [$role_id]));
        $this->assertRedirect($response, 'config.role.overview', [
            "error" => RoleEnum::ROLE_NOT_FOUND_MESSAGE,
        ]);
    }

    //| save role tests
    public function testSaveNewPermissionValid(): void
    {
        $route = route('config.role.save');
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $description = $this->faker->regexify('[A-Za-z0-9]{48}');


        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $name,
                'description' =>  $description,
            ]);
        $this->assertRedirect($response, 'config.role.overview', [
            "message" => RoleEnum::ROLE_SAVED_MESSAGE,
        ]);

        $role = Role::where('name', $name)->where('description', $description)->first();
        $this->assertNotNull($role);
        $this->assertEquals($name, $role->name);
        $this->assertEquals($description, $role->description);
    }

    public function testSaveModifiedPermissionValid(): void
    {
        $route = route('config.role.save');
        $role_id = $this->createRandomEntity(Role::class)->id;
        $name = $this->faker->regexify('[A-Za-z0-9]{26}');
        $description = $this->faker->regexify('[A-Za-z0-9]{48}');

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' => $role_id,
                'name' =>  $name,
                'description' =>  $description,
            ]);
        $this->assertRedirect($response, 'config.role.overview', [
            "message" => RoleEnum::ROLE_SAVED_MESSAGE,
        ]);

        $role = Role::find($role_id);
        $this->assertNotNull($role);
        $this->assertEquals($role_id, $role->id);
        $this->assertEquals($name, $role->name);
        $this->assertEquals($description, $role->description);
    }

    public function testSaveRoleIdValidation(): void
    {
        $route = route('config.role.save');
        $role = $this->getRandomEntity(Role::class);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'id' =>  $role->id,
            ]);
        $this->assertValidationValid('id');

        //exists
        $this->post($route, [
            'id' => $this->getFalseId(Role::class),
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

    public function testSaveRoleNameValidation(): void
    {
        $route = route('config.role.save');

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' =>  $this->faker->regexify('[A-Za-z0-9]{20}'),
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
                'name' => $this->faker->regexify('[A-Za-z0-9]{30}'),
            ]);
        $this->assertValidationTooLong('name', 28);

        //is string
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'name' => 44,
            ]);
        $this->assertValidationString('name');
    }

    public function testSaveRoleDescriptionValidation(): void
    {
        $route = route('config.role.save');

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

    //| delete role tests
    public function testDeleteRole(): void
    {
        $role = $this->createRandomEntity(Role::class);
        $route = route('config.role.delete', [$role->id]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);

        $this->assertNull(Role::where('id', $role->id)->first());
        $this->assertRedirect($response, 'config.role.overview', [
            "message" => RoleEnum::ROLE_DELETED_MESSAGE,
        ]);
    }

    public function testDeleteRoleNotFound(): void
    {
        $role_id = $this->getFalseId(Role::class);
        $route = route('config.role.delete', [$role_id]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);

        $this->assertRedirect($response, 'config.role.overview', [
            "error" => RoleEnum::ROLE_NOT_FOUND_MESSAGE,
        ]);
    }

    public function testDeleteRoleUsedByUser(): void
    {
        $role = $this->createRandomEntity(Role::class);
        $user = $this->createRandomEntity(User::class);
        $user->role_id = $role->id;
        $user->save();

        $route = route('config.role.delete', [$role->id]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);

        $this->assertNotNull(Role::where('id', $role->id)->first());
        $this->assertRedirect($response, 'config.role.overview', [
            "error" => RoleEnum::USER_HAS_ROLE_MESSAGE,
        ]);
    }

    //| toggle permission tests
    public function testTogglePermissionSetTrue(): void
    {
        $role = $this->getRandomEntity(Role::class);
        $permission = $this->getRandomEntity(Permission::class);

        $route = route('config.role.permission.toggle', [
            $role->id,
            $permission->id,
        ]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'hasPermission' =>  true,
            ]);
        $response->assertStatus(200);
        $this->assertEquals('1', Role::where('id', $role->id)->first()->hasPermission($permission));
    }

    public function testTogglePermissionSetFalse(): void
    {
        $role = $this->getRandomEntity(Role::class);
        $permission = $this->getRandomEntity(Permission::class);

        $link = new RolePermission();
        $link->role_id = $role->id;
        $link->permission_id = $permission->id;
        $link->save();

        $route = route('config.role.permission.toggle', [
            $role->id,
            $permission->id,
        ]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'hasPermission' =>  false,
            ]);
        $response->assertStatus(200);
        $this->assertEquals('0', Role::where('id', $role->id)->first()->hasPermission($permission));
    }

    public function testTogglePermissionHasPermissionValidation(): void
    {
        $route = route('config.role.permission.toggle', [
            $this->getFalseId(Role::class),
            $this->getFalseId(Permission::class)
        ]);

        //valid
        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'hasPermission' =>  true,
            ]);
        $json = (is_array($response->json())) ? $response->json() : [];
        $this->assertValidationValid('hasPermission', $json);

        //required
        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $response->assertStatus(400);
        $this->assertValidationRequired('hasPermission', $response->json());

        //is string
        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'hasPermission' => 'string',
            ]);
        $response->assertStatus(400);
        $this->assertValidationBoolean('hasPermission', $response->json());
    }

    public function testTogglePermissionInvalidRole(): void
    {
        $route = route('config.role.permission.toggle', [
            $this->getFalseId(Role::class),
            $this->getFalseId(Permission::class)
        ]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'hasPermission' =>  true,
            ]);
        $response->assertStatus(404);
        $this->assertEquals(RoleEnum::ROLE_NOT_FOUND_MESSAGE, $response->json());
    }

    public function testTogglePermissionInvalidPermission(): void
    {
        $route = route('config.role.permission.toggle', [
            $this->getRandomEntity(Role::class)->id,
            $this->getFalseId(Permission::class)
        ]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'hasPermission' =>  true,
            ]);
        $response->assertStatus(404);
        $this->assertEquals(PermissionEnum::NOT_FOUND_MESSAGE, $response->json());
    }
}
