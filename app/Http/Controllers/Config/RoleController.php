<?php

namespace App\Http\Controllers\Config;

use App\Enum\Auth\PermissionEnum;
use App\Enum\Auth\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    public function overview()
    {
        return view('config.role.overview', $this->getBaseVariables());
    }

    public function new()
    {
        return view('config.role.modify', $this->getBaseVariables());
    }

    public function modify(int $id)
    {
        $role = Role::find($id);
        if ($role === null) {
            // todo custom error screen
            return redirect(route('config.role.overview'))->with("error", RoleEnum::ROLE_NOT_FOUND_MESSAGE);
        }

        return view('config.role.modify', array_merge($this->getBaseVariables(), [
            'role' => $role,
        ]));
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:auth__role,id',
            'name' => 'required|string|max:28',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->all())
                ->withErrors($validator);
        }

        $role = null;
        if ($request->id !== null) {
            $role = Role::find($request->id);
        } else {
            $role = new Role();
        }

        $role->name = $request->name;
        $role->description = $request->description;
        $role->save();

        return redirect(route('config.role.overview'))->with("message", RoleEnum::ROLE_SAVED_MESSAGE);
    }

    public function delete(int $id)
    {
        $role = Role::where('id', $id)->first();
        if ($role !== null) {
            if ($role->Users->count() > 0) {
                return redirect(route('config.role.overview'))->with("error", RoleEnum::USER_HAS_ROLE_MESSAGE);
            }

            $role->delete();
            return redirect(route('config.role.overview'))->with("message", RoleEnum::ROLE_DELETED_MESSAGE);
        } else {
            return redirect(route('config.role.overview'))->with("error", RoleEnum::ROLE_NOT_FOUND_MESSAGE);
        }
    }

    public function togglePermission(Request $request, int $role_id, int $permission_id)
    {
        $validator = Validator::make($request->all(), [
            'hasPermission' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->messages(), 400);
        }

        $role = Role::find($role_id);
        if ($role === null) {
            return response()->json(RoleEnum::ROLE_NOT_FOUND_MESSAGE, 404);
        }

        $permission = Permission::find($permission_id);
        if ($permission === null) {
            return response()->json(PermissionEnum::NOT_FOUND, 404);
        }

        if ($role->hasPermission($permission)) {
            if ($request->hasPermission == false) {
                $link = RolePermission::select()
                    ->where('role_id', $role_id)
                    ->where('permission_id', $permission_id)
                    ->delete();
            }
        } else {
            if ($request->hasPermission == true) {
                $link = new RolePermission();
                $link->role_id = $role_id;
                $link->permission_id = $permission_id;
                $link->save();
            }
        }

        return response()->json('Success', 200);
    }
}
