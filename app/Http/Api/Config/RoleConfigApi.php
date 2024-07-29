<?php

namespace App\Http\Api\Config;

use App\Enum\Auth\PermissionEnum;
use App\Enum\Auth\RoleEnum;
use App\Enum\Auth\UserEnum;
use App\Http\Api\AbstractApi;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\RolePermission;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RoleConfigApi extends AbstractApi
{
    /**
     * Add the specified permission to the specified role
     * @param int $role_id The id of role to add the permission for
     * @param int $permission_id The id of the permission to add
     * @return JsonResponse True or an error in json format
     */
    public function grantPermission(int $role_id, int $permission_id): JsonResponse
    {
        $role = Role::find($role_id);
        if ($role === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, RoleEnum::NOT_FOUND);
        }

        $permission = Permission::find($permission_id);
        if ($permission === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, PermissionEnum::NOT_FOUND);
        }

        $link = RolePermission::where('role_id', $role->id)->where('permission_id', $permission->id)->first();
        if ($link === null) {
            $link = new RolePermission();
            $link->role_id = $role->id;
            $link->permission_id = $permission->id;
            $link->save();
        }

        return $this->respond(Response::HTTP_OK, RoleEnum::PERMISSION_GRANTED, true);
    }

    /**
     * Add the specified permission to the specified role
     * @param int $role_id The id of role to add the permission for
     * @param int $permission_id The id of the permission to add
     * @return JsonResponse True or an error in json format
     */
    public function revokePermission(int $role_id, int $permission_id): JsonResponse
    {
        $role = Role::find($role_id);
        if ($role === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, RoleEnum::NOT_FOUND);
        }

        $permission = Permission::find($permission_id);
        if ($permission === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, PermissionEnum::NOT_FOUND);
        }

        $link = RolePermission::where('role_id', $role->id)->where('permission_id', $permission->id)->first();
        $link?->delete();

        return $this->respond(Response::HTTP_OK, RoleEnum::PERMISSION_REVOKED, true);
    }
}
