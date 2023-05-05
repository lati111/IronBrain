<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function showSignup()
    {
        return view('authentication.signup', $this->getBaseVariables());
    }

    public function saveSignup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:28',
            'email' => 'required|email|max:40|unique:user,email',
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
            'password' => Hash::make($request->password)
        ]);

        return redirect(route('home.show'))->with("message", "Account Created");
    }
}
