<?php

namespace App\Http\Controllers\Config;

use App\Enum\Auth\UserEnum;
use App\Http\Controllers\Controller;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function overview()
    {
        return view('config.user.overview', array_merge($this->getBaseVariables(), [
            'roles' => Role::all(),
        ]));
    }

    public function deactivate(string $uuid)
    {
        $user = User::find($uuid);
        if ($user !== null) {
            $user->delete();
            return redirect(route('config.user.overview'))->with("message", UserEnum::USER_DEACTIVATED_MESSAGE);
        } else {
            return redirect(route('config.user.overview'))->with("error", UserEnum::USER_NOT_FOUND_MESSAGE);
        }
    }

    public function setRole(Request $request, string $uuid)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'nullable|integer|exists:auth__role,id',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator);
        }

        $user = User::find($uuid);
        if ($user === null) {
            return back()
                ->with('error', UserEnum::USER_NOT_FOUND_MESSAGE);
        }

        $user->role_id = $request->role_id;
        $user->save();

        return redirect(route('config.user.overview'))
            ->with("message", UserEnum::USER_ROLE_CHANGED_MESSAGE);
    }
}
