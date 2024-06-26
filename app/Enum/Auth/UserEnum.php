<?php

namespace App\Enum\Auth;

class UserEnum
{
    //| Config strings
    public const USER_DEACTIVATED_MESSAGE = "User was deactivated";
    public const USER_ROLE_CHANGED_MESSAGE = "User role was changed";
    public const USER_NOT_FOUND_MESSAGE = "Invalid user";

    //| Login strings
    public const LOGIN_SUCCESS_MESSAGE = "You have been logged in";
    public const LOGIN_FAILED_MESSAGE = "Username or password is incorrect";
    public const LOGOUT_MESSAGE = "Logged out succesfully";
    public const SIGNUP_SUCCESS_MESSAGE = "Account Created";
    public const PASSWORDS_NOT_MATCHING_MESSAGE = "Passwords must match";
    public const MUST_BE_LOGGED_IN_MESSAGE = 'You must be logged in to access this route';

}
