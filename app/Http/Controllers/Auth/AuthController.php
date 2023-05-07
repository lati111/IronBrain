<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use splitbrain\RingIcon\RingIconSVG;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class AuthController extends Controller
{
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
            'name' => 'required|string|max:28',
            'email' => 'required|email|max:40|unique:auth__user,email',
            'password' => 'required|string|min:8',
            'repeat_password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->all())
                ->withErrors($validator);
        }

        $request->validate([
            'password' => ['required', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->uncompromised()]
        ]);

        if ($request->password !== $request->repeat_password) {
            return back()
                ->withInput($request->all())
                ->with('error', 'Passwords must match');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        //| profile picture
        $path = sprintf('img/profile/%s', $user->uuid);
        if (is_dir($path) === false) {
            File::makeDirectory($path);
        }

        $profilePicture = new RingIconSVG(128, 3);
        $profilePicture->setMono(true);
        $profilePicture->createImage($request->name . $request->email, sprintf('img/profile/%s/pfp.svg', $user->uuid));
        $user->profile_picture = sprintf('%s/pfp.svg', $user->uuid);
        $user->save();

        return redirect(route('auth.login.show'))->with("message", "Account Created");
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
            'email' => 'required',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only(['email', 'password']))) {
            return redirect()->route("home.show")
                ->with('message', 'Log in successful');
        } else {
            return back()
                ->with('error', 'Username or password is incorrect');
        }
    }

    public function logout(): View | RedirectResponse
    {
        if (Auth::user() === null) {
            return redirect(route('home.show'));
        }

        Auth::logout();
        return redirect()->route("auth.login.show")
                ->with('message', 'Logged out succesfully');
    }
}
