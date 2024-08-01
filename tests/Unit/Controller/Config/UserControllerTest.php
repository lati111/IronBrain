<?php

namespace Tests\Unit\Controller\Config;

use App\Enum\Auth\UserEnum;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use Tests\Unit\Controller\AbstractControllerUnitTester;

class UserControllerTest extends AbstractControllerUnitTester
{
    //| show overview test
    public function testOverviewShow(): void
    {
        $response = $this
            ->actingAs($this->getAdminUser())
            ->get(route('config.user.overview'));
        $this->assertView($response, 'config.user.overview', [
            'roles' => Role::all()
        ]);
    }

    //| delete user test
    public function testDeleteUserValid(): void
    {
        $user = $this->createRandomEntity(User::class);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post(route('config.user.delete', [$user->uuid]));
        $this->assertNull(User::where('email', $user->email)->first());
        $this->assertRedirect($response, 'config.user.overview', [
            "message" => UserEnum::USER_DEACTIVATED_MESSAGE,
        ]);
    }

    public function testDeleteUserNotFound(): void
    {
        $route = route('config.user.delete', $this->getFalseUuid(User::class));

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertRedirect($response, 'config.user.overview', [
            "error" => UserEnum::NOT_FOUND,
        ]);
    }

    //| set role test
    public function testSetRoleValid(): void
    {
        $user = $this->getRandomEntity(User::class);
        $role = $this->getRandomEntity(Role::class);
        $route = route('config.user.role.set', [$user->uuid]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'role_id' => $role->id,
            ]);

        $this->assertRedirect($response, 'config.user.overview', [
            "message" => UserEnum::USER_ROLE_CHANGED_MESSAGE,
        ]);

        $user = User::where('email', $user->email)->first();
        $this->assertEquals($role->id, $user->role_id);
    }

    public function testSetRoleRoleValidation(): void
    {
        $role = $this->getRandomEntity(Role::class);
        $route = route('config.user.role.set', [$this->getFalseUuid(User::class)]);

        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'role_id' =>  $role->id,
            ]);
        $this->assertValidationValid('role_id');

        //is nullable
        $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertValidationValid('role_id');

        //is integer
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'role_id' => 'fake string',
            ]);
        $this->assertValidationInteger('role_id');

        //exists in
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'role_id' => $this->getFalseId(Role::class),
            ]);
        $this->assertValidationExists('role_id');
    }

    public function testSetRoleUserNotFound(): void
    {
        $role = $this->getRandomEntity(Role::class);
        $route = route('config.user.role.set', [$this->getFalseUuid(User::class)]);

        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'role_id' =>  $role->id,
            ]);
        $this->assertEquals(UserEnum::NOT_FOUND, session('error'));
    }
}
