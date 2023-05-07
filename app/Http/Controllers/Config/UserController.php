<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Auth\Permission;
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

    public function delete(int $uuid)
    {
        $user = User::find($uuid);
        if ($user !== null) {
            $user->active = false;
            $user->save();
            return redirect(route('config.user.overview'))->with("message", "User was deactivated");
        } else {
            return redirect(route('config.user.overview'))->with("error", "Invalid user");
        }
    }

    public function setPermission(Request $request, string $uuid) {
        $validator = Validator::make($request->all(), [
            'role_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator);
        }

        $user = User::find($uuid);
        if ($user === null) {
            return back()
                ->with('error', 'User does not exist');
        }

        $user->role_id = $request->role_id;
        $user->save();

        return back()
            ->with('error', 'Role changed');
    }
}
