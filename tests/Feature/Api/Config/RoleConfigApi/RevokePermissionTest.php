<?php

namespace Tests\Feature\Api\Config\RoleConfigApi;

use App\Enum\Auth\PermissionEnum;
use App\Enum\Auth\RoleEnum;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\RolePermission;
use Tests\Feature\Api\AbstractApiFeatureTester;

class RevokePermissionTest extends AbstractApiFeatureTester
{
    protected Role $role;
    protected Permission $permission;

    public function setUp(): void
    {
        parent::setUp();

        $this->role = new Role();
        $this->role->name = 'Test Role';
        $this->role->description = 'Role for testing';
        $this->role->is_admin = false;
        $this->role->save();

        $this->permission = Permission::first();
    }

    protected function getOperationType(): string
    {
        return 'POST';
    }

    protected function getRoute(): string
    {
        return route('api.config.role.permissions.revoke', [$this->role->id, $this->permission->id]);
    }

    protected function getModel(): Role
    {
        return new Role();
    }

    private function getFakeRoleId(): int
    {
        return (Role::max('id') ?? 0) + 1;
    }

    private function getFakePermissionId(): int
    {
        return (Permission::max('id') ?? 0) + 1;
    }

    public function test_role_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post(route('api.config.role.permissions.revoke', [$this->getFakeRoleId(), $this->permission->id]));

        $response->assertNotFound();
        $this->assertApiMessage(RoleEnum::NOT_FOUND, $response);
    }

    public function test_permission_not_found(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post(route('api.config.role.permissions.revoke', [$this->role->id, $this->getFakePermissionId()]));

        $response->assertNotFound();
        $this->assertApiMessage(PermissionEnum::NOT_FOUND, $response);
    }

    public function test_revoke_permission(): void
    {
        $link = new RolePermission();
        $link->role_id = $this->role->id;
        $link->permission_id = $this->permission->id;
        $link->save();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute());

        $response->assertOk();
        $this->assertApiMessage(RoleEnum::PERMISSION_REVOKED, $response);

        $this->assertNull(
            RolePermission::where('role_id', $this->role->id)
                ->where('permission_id', $this->permission->id)
                ->first()
        );
    }

    public function test_revoke_permission_not_granted(): void
    {
        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute());

        $response->assertOk();
        $this->assertApiMessage(RoleEnum::PERMISSION_REVOKED, $response);
    }
}
