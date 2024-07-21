<?php

namespace App\Http\Controllers\Auth;

use App\Enum\Auth\UserEnum;
use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Service\AvatarGenerator;
use App\Service\AvatarGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AuthController extends Controller
{
    private AvatarGeneratorService $avatarGeneratorService;

    public function __construct(
        AvatarGeneratorService $avatarGeneratorService
    ) {
        $this->avatarGeneratorService = $avatarGeneratorService;
    }

    public function showSignup(): View | RedirectResponse
    {
        if (Auth::user() !== null) {
            return redirect(route('home.show'));
        }

        return view('authentication.signup', $this->getBaseVariables());
    }

    public function saveSignup(Request $request): RedirectResponse
    {
        if (Auth::user() !== null) {
            return redirect(route('home.show'));
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:28',
            'email' => 'nullable|email|max:40|unique:auth__user,email',
            'password' => ['required', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()],
            'repeat_password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->all())
                ->withErrors($validator);
        }

        if ($request->password !== $request->repeat_password) {
            return back()
                ->withInput($request->all())
                ->with('error', UserEnum::PASSWORDS_NOT_MATCHING_MESSAGE);
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $this->avatarGeneratorService->generateProfilePicture($user);

        return redirect(route('auth.login.show'))->with("message", UserEnum::SIGNUP_SUCCESS_MESSAGE);
    }

    public function showLogin(): View | RedirectResponse
    {
        if (Auth::user() !== null) {
            return redirect(route('home.show'));
        }

        return view('authentication.login', $this->getBaseVariables());
    }

    public function attemptLogin(Request $request): RedirectResponse
    {
        if (Auth::user() !== null) {
            return redirect(route('home.show'));
        }

        $request->validate([
            'username' => 'required',
            'password' => 'required',
            'remember_me' => 'nullable'
        ]);

        $remember = false;
        if ($request->remember_me === "on") {
            $remember = true;
        }

        if (Auth::attempt($request->only(['username', 'password']), $remember)) {
            return redirect()->route("home.show")
                ->with('message', UserEnum::LOGIN_SUCCESS_MESSAGE);
        } else {
            return back()
                ->with('error', UserEnum::LOGIN_FAILED_MESSAGE);
        }
    }

    public function logout(): View | RedirectResponse
    {
        if (Auth::user() === null) {
            return redirect(route('home.show'));
        }

        Auth::logout();
        return redirect()->route("auth.login.show")
                ->with('message', UserEnum::LOGOUT_MESSAGE);
    }
}
