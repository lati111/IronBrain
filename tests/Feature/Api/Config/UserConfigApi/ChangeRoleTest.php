<?php

namespace Tests\Feature\Api\Config\UserConfigApi;

use App\Enum\Auth\RoleEnum;
use App\Enum\Auth\UserEnum;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use Tests\Feature\Api\AbstractApiFeatureTester;

class ChangeRoleTest extends AbstractApiFeatureTester
{
    protected Role $role;
    protected User $targetUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->role = new Role();
        $this->role->name = 'Test Role';
        $this->role->description = 'Role for testing';
        $this->role->is_admin = false;
        $this->role->save();

        $this->targetUser = $this->createRandomEntity(User::class);
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return route('api.config.users.change-role', [$this->targetUser->uuid, $this->role->id]);
    }

    protected function getModel(): User
    {
        return new User();
    }

    private function getFakeRoleId(): int
    {
        return (Role::max('id') ?? 0) + 1;
    }

    public function test_user_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post(route('api.config.users.change-role', [$this->getFalseUuid(User::class), $this->role->id]));

        $response->assertNotFound();
        $this->assertApiMessage(UserEnum::NOT_FOUND, $response);
    }

    public function test_role_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post(route('api.config.users.change-role', [$this->targetUser->uuid, $this->getFakeRoleId()]));

        $response->assertNotFound();
        $this->assertApiMessage(RoleEnum::NOT_FOUND, $response);
    }

    public function test_change_role(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute());

        $response->assertOk();
        $this->assertApiMessage(UserEnum::ROLE_CHANGED, $response);

        $this->targetUser->refresh();
        $this->assertEquals($this->role->id, $this->targetUser->role_id);
    }
}
