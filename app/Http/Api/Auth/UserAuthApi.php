<?php

namespace App\Http\Api\Auth;

use App\Enum\Auth\PermissionEnum;
use App\Enum\Auth\RoleEnum;
use App\Enum\Auth\UserEnum;
use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\RolePermission;
use App\Models\Auth\User;
use App\Service\AvatarGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class UserAuthApi extends AbstractApi
{
    /** @var AvatarGeneratorService The avatar generator service */
    private AvatarGeneratorService $avatarGeneratorService;

    public function __construct(
        AvatarGeneratorService $avatarGeneratorService
    ) {
        $this->avatarGeneratorService = $avatarGeneratorService;
    }

    /**
     * Attempt to log in
     * @param Request $request The request parameters as passed by Laravel
     * @return JsonResponse True or an error in json format
     */
    public function attemptLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
            'remember_me' => 'nullable'
        ]);

        if ($validator->fails()) {
            $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $remember = false;
        if ($request->remember_me === "on") {
            $remember = true;
        }

        if (Auth::attempt($request->only(['username', 'password']), $remember)) {
            return $this->respond(Response::HTTP_OK, UserEnum::LOGIN_SUCCESS_MESSAGE, true, [
                'Location' => route('home', ['message' => UserEnum::LOGIN_SUCCESS_MESSAGE]),
            ]);
        } else {
            return $this->respond(Response::HTTP_UNAUTHORIZED, UserEnum::LOGIN_FAILED_MESSAGE);
        }
    }

    /**
     * Add the specified permission to the specified role
     * @param Request $request The request parameters as passed by Laravel
     * @return JsonResponse True or an error in json format
     */
    public function createAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:28',
            'email' => 'nullable|email|max:40|unique:auth__user,email',
            'password' => ['required', 'confirmed', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()],
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $this->avatarGeneratorService->generateProfilePicture($user);

        return $this->respond(Response::HTTP_CREATED, UserEnum::SIGNUP_SUCCESS_MESSAGE, true, [
            'Location' => route('auth.login', ['message' => UserEnum::SIGNUP_SUCCESS_MESSAGE]),
        ]);
    }
}
