<?php

namespace App\Http\Controllers\Config;

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
            return redirect(route('config.permission.overview'))->with("error", "That permission does not exist");
        }

        return view('config.permission.modify', array_merge($this->getBaseVariables(), [
            'permission' => $permission,
        ]));
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer',
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
        if ($request->id !== null) {
            $permission = Permission::find($request->id);
        } else {
            $permission = new Permission();
        }

        $permission->name = $request->name;
        $permission->group = $request->group;
        $permission->description = $request->description;
        $permission->save();

        return redirect(route('config.permission.overview'))->with("message", "Changes saved");
    }

    public function delete(int $id)
    {
        $permission = Permission::find($id);
        if ($permission !== null) {
            $permission->delete();
            return redirect(route('config.permission.overview'))->with("message", "Permission was deleted");
        } else {
            return redirect(route('config.permission.overview'))->with("error", "Invalid permission");
        }
    }
}
