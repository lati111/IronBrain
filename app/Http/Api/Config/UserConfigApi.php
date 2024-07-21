<?php

namespace App\Http\Api\Config;

use App\Enum\Auth\RoleEnum;
use App\Enum\Auth\UserEnum;
use App\Http\Api\AbstractApi;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class UserConfigApi extends AbstractApi
{
    /**
     * Change a user's rule
     * @param string $user_uuid The user whose role to change
     * @param int $role_id The role to give to the user
     * @return JsonResponse True or an error in json format
     */
    public function changeRole(string $user_uuid, int $role_id): JsonResponse
    {
        $user = User::find($user_uuid);
        if ($user === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, UserEnum::NOT_FOUND);
        }

        $role = Role::find($role_id);
        if ($role === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, RoleEnum::NOT_FOUND);
        }

        $user->role_id = $role->id;
        $user->save();

        return $this->respond(Response::HTTP_OK, UserEnum::ROLE_CHANGED, true);
    }
}
