<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Config\Permission;
use App\Models\Config\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    public function overview()
    {
        return view('config.permission.overview', array_merge($this->getBaseVariables(), [
        ]));
    }

    public function new()
    {
        return view('config.permission.modify', $this->getBaseVariables());
    }

    public function modify(int $id)
    {
        $project = Project::find($id);
        if ($project === null) {
            // todo custom error screen
            return redirect(route('config.projects.overview'))->with("error", "That route does not exist");
        }

        return view('config.projects.modify', array_merge($this->getBaseVariables(), [
            'project' => $project,
        ]));
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integrer',
            'name' => 'required|string|max:64',
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

    public function delete(int $id) {
        $project = Project::find($id);
        if ($project !== null) {
            $project->delete();
            return redirect(route('config.projects.overview'))->with("message", "Project was deleted");
        } else {
            // todo custom error screen
            return redirect(route('config.projects.overview'))->with("error", "Invalid project");
        }
    }
}
