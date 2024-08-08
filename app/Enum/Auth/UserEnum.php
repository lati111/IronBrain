<?php

namespace App\Enum\Auth;

class UserEnum
{
    //| Config strings
    public const USER_DEACTIVATED_MESSAGE = "User was deactivated";
    public const ROLE_CHANGED = "User role was changed";
    public const NOT_FOUND = "Invalid user";

    //| Login strings
    public const LOGGED_IN = "You have been logged in";
    public const INVALID_LOGIN = "Username or password is incorrect";
    public const LOGOUT_MESSAGE = "Logged out succesfully";
    public const SIGNUP_SUCCESS_MESSAGE = "Account Created";
    public const PASSWORDS_NOT_MATCHING_MESSAGE = "Passwords must match";
    public const MUST_BE_LOGGED_IN_MESSAGE = 'You must be logged in to access this route';

}
