<?php

namespace App\Http\Controllers\Config;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class ProjectController
{
    public function overview()
    {
        $page = isset($_GET["page"]) ? $_GET["page"] : 1;
        $perPage = isset($_GET["perPage"]) ? $_GET["perPage"] : 10;
        $projects = Project::offset(($page - 1) * $perPage)->limit($perPage)->get();
        $projectCount = Project::all()->count();

        return view('config.projects.overview', [
            "projectCount" => $projectCount,
            "projects" => $projects,
            "perPage" => $perPage,
            "page" => $page,
        ]);
    }

    public function new()
    {
        return view('config.projects.modify');
    }

    public function modify(int $id)
    {
        $project = Project::find($id);
        if ($project === null) {
            // todo custom error screen
            return redirect(route('config.projects.overview'))->with("error", "That route does not exist");
        }

        return view('config.projects.modify', [
            'project' => $project,
        ]);
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:project,id',
            'thumbnail' => 'nullable|mimes:png,jpg,jpeg,svg,webp|max:240',
            'name' => 'required|string|max:64',
            'route' => 'required|string|max:255',
            'description' => 'required|string',
            'permission' => 'nullable|string"exists:permission,permission',
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput($request->all())
                ->withErrors($validator);
        }

        if (Route::has($request->route) === false) {
            return back()
                ->withInput($request->all())
                ->with('error', "Route does not exist");
        }

        $project = null;
        if ($request->id !== null) {
            $project = Project::find($request->id);
        } else {
            $project = new Project();
            if ($request->thumbnail) {
                return back()
                    ->withInput($request->all())
                    ->with('error', 'Thumbnail is required');
            }
        }

        if ($request->thumbnail !== null) {
            $filename = sprintf(
                '%s.%s',
                $request->name,
                $request->thumbnail->extension()
            );
            $request->thumbnail->storeAs('project/thumbnail/' . $filename);
            $project->thumbnail = $filename;
        }

        $project->name = $request->name;
        $project->route = $request->route;
        $project->description = $request->description;
        $project->permission = $request->permission;
        $project->save();

        return redirect(route('config.projects.overview'))->with("message", "Changes saved");
    }
}
