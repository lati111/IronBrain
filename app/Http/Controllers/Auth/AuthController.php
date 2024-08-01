<?php

namespace App\Http\Controllers\Auth;

use App\Enum\Auth\UserEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{

    /**
     * Show the login page. Auto-redirects to home if logged in
     * @return View|RedirectResponse The login page
     */
    public function login(): View | RedirectResponse
    {
        if (Auth::user() !== null) {
            return redirect(route('home'));
        }

        return $this->view('authentication.login');
    }

    /**
     * Show the signup page. Auto-redirects to home if logged in
     * @return View|RedirectResponse The signup page
     */
    public function signup(): View | RedirectResponse
    {
        if (Auth::user() !== null) {
            return redirect(route('home'));
        }

        return $this->view('authentication.signup');
    }

    /**
     * Logs the user out and redirects them to the login page
     * @return View|RedirectResponse The login page
     */
    public function logout(): View | RedirectResponse
    {
        if (Auth::user() === null) {
            return redirect(route('home'));
        }

        Auth::logout();
        return redirect()->route("auth.login")
                ->with('message', UserEnum::LOGOUT_MESSAGE);
    }
}
