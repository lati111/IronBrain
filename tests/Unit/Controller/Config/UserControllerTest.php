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
        $user = $this->getRandomUser();

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post(route('config.user.delete', [$user->uuid]));
        $this->assertEquals('0', User::where('email', $user->email)->first()->active);
        $this->assertRedirect($response, 'config.user.overview', [
            "message" => UserEnum::USER_DEACTIVATED_MESSAGE,
        ]);
    }

    public function testDeleteUserNotFound(): void
    {
        $route = route('config.user.delete', -4);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route);
        $this->assertRedirect($response, 'config.user.overview', [
            "error" => UserEnum::USER_NOT_FOUND_MESSAGE,
        ]);
    }

    //| set role test
    public function testSetRoleValid(): void
    {
        $user = $this->getRandomUser();
        $role_id = Role::select()->first()->id;
        $route = route('config.user.role.set', [$user->uuid]);

        $response = $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'role_id' => $role_id,
            ]);

        $this->assertRedirect($response, 'config.user.overview', [
            "message" => UserEnum::USER_ROLE_CHANGED_MESSAGE,
        ]);

        $user = User::where('email', $user->email)->first();
        $this->assertEquals($role_id, $user->role_id);
    }

    public function testSetRoleRoleValidation(): void
    {
        $role_id = Role::select()->first()->id;
        $route = route('config.user.role.set', [$this->getFalseUser()]);
        //valid
        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'role_id' =>  $role_id,
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
                'role_id' => $this->getFalseRole(),
            ]);
        $this->assertValidationExists('role_id');
    }

    public function testSetRoleUserNotFound(): void
    {
        $role_id = Role::select()->first()->id;
        $route = route('config.user.role.set', [$this->getFalseUser()]);

        $this
            ->actingAs($this->getAdminUser())
            ->post($route, [
                'role_id' =>  $role_id,
            ]);
        $this->assertEquals(UserEnum::USER_NOT_FOUND_MESSAGE, session('error'));
    }

    //| getters
    private function getRandomUser(): User|null
    {
        return User::where('email', '!=', 'admin@test.nl')->first();
    }

    private function getFalseUser(): string
    {
        $saved_uuid = null;
        while ($saved_uuid === null) {
            $uuid = $this->faker->uuid();
            if (User::where('uuid', $uuid)->count() === 0) {
                $saved_uuid = $uuid;
            }
        }

        return $saved_uuid;
    }

    private function getFalseRole(): int
    {
        $saved_id = null;
        while ($saved_id === null) {
            $id = $this->faker->randomNumber();
            if (Role::where('id', $id)->count() === 0) {
                $saved_id = $id;
            }
        }

        return $saved_id;
    }
}
