<?php

namespace App\Http\Controllers\Config;

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
        return view('config.role.overview', array_merge($this->getBaseVariables(), []));
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
            return redirect(route('config.role.overview'))->with("error", "That role does not exist");
        }

        return view('config.role.modify', array_merge($this->getBaseVariables(), [
            'role' => $role,
        ]));
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
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

        return redirect(route('config.role.overview'))->with("message", "Changes saved");
    }

    public function delete(int $id)
    {
        $permission = Permission::find($id);
        if ($permission !== null) {
            $permission->delete();
            return redirect(route('config.role.overview'))->with("message", "Permission was deleted");
        } else {
            return redirect(route('config.role.overview'))->with("error", "Invalid permission");
        }
    }

    public function togglePermission(Request $request, int $role_id, int $permission_id) {
        $validator = Validator::make($request->all(), [
            'hasPermission' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->messages(), 400);
        }

        $role = Role::find($role_id);
        if ($role === null) {
            return response()->json('Role does not exist', 404);
        }

        $permission = Permission::find($permission_id);
        if ($permission === null) {
            return response()->json('Permission does not exist', 404);
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
