<?php

namespace App\Enum\Auth;

class RoleEnum
{
    public const ROLE_SAVED_MESSAGE = "Saved role";
    public const NOT_FOUND = "Invalid role";
    public const ROLE_DELETED_MESSAGE = "Role was deleted";
    public const USER_HAS_ROLE_MESSAGE = "This role is assigned to at least one user";

    public const PERMISSION_GRANTED = "The permission has been granted.";
    public const PERMISSION_REVOKED = "The permission has been revoked.";

}
