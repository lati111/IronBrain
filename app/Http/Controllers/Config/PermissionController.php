<?php

namespace App\Http\Controllers\Config;

use App\Enum\Auth\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Auth\Permission;
use App\Models\Config\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    public function overview()
    {
        return view('config.permission.overview', array_merge($this->getBaseVariables(), []));
    }

    public function new()
    {
        return view('config.permission.modify', $this->getBaseVariables());
    }

    public function modify(int $id)
    {
        $permission = Permission::find($id);
        if ($permission === null) {
            // todo custom error screen
            return redirect(route('config.permission.overview'))->with("error", PermissionEnum::NOT_FOUND_MESSAGE);
        }

        return view('config.permission.modify', array_merge($this->getBaseVariables(), [
            'permission' => $permission,
        ]));
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
            'permission' => 'required|string|max:128',
            'name' => 'required|string|max:48',
            'group' => 'required|string|max:64',
            'description' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->all())
                ->withErrors($validator);
        }

        $permission = null;
        $isUnique = true;
        if ($request->id !== null) {
            $permission = Permission::find($request->id);
            if (Permission::where('permission', $request->permission)->where('id', "!=", $request->id)->count() > 0) {
                $isUnique = false;
            }
        } else {
            $permission = new Permission();
            if (Permission::where('permission', $request->permission)->count() > 0) {
                $isUnique = false;
            }
        }

        if ($isUnique === false) {
            return back()
                ->withInput($request->all())
                ->with('error', 'Permission must be unique');
        }

        $permission->permission = $request->permission;
        $permission->name = $request->name;
        $permission->group = $request->group;
        $permission->description = $request->description;
        $permission->save();

        return redirect(route('config.permission.overview'))->with("message", PermissionEnum::SAVED_MESSAGE);
    }

    public function delete(int $id)
    {
        $permission = Permission::find($id);
        if ($permission !== null) {
            $permission->delete();
            return redirect(route('config.permission.overview'))->with("message", PermissionEnum::DELETED_MESSAGE);
        } else {
            return redirect(route('config.permission.overview'))->with("error", PermissionEnum::NOT_FOUND_MESSAGE);
        }
    }
}
