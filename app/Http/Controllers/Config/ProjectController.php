<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    public function overview()
    {
        $page = isset($_GET["page"]) ? $_GET["page"] : 1;
        $perPage = isset($_GET["perPage"]) ? $_GET["perPage"] : 10;
        $projects = Project::offset(($page - 1) * $perPage)->limit($perPage)->get();
        $projectCount = Project::all()->count();

        return view('config.projects.overview', array_merge($this->getBaseVariables(), [
            "projectCount" => $projectCount,
            "projects" => $projects,
            "perPage" => $perPage,
            "page" => $page,
        ]));
    }

    public function new()
    {
        return view('config.projects.modify', $this->getBaseVariables());
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
            'id' => 'nullable|exists:project,id',
            'thumbnail' => 'nullable|mimes:png,jpg,jpeg,svg,webp|max:240',
            'name' => 'required|string|max:64',
            'route' => 'required|string|max:255',
            'description' => 'required|string',
            'permission' => 'nullable|string"exists:permission,permission',
            'order' => 'nullable|integer',
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
            if ($request->thumbnail === null && $request->inOverview === "on") {
                return back()
                    ->withInput($request->all())
                    ->with('error', 'Thumbnail is required');
            }
        }

        if ($request->thumbnail !== null && $request->inOverview === "on") {
            $filename = sprintf(
                '%s.%s',
                $request->name,
                $request->thumbnail->extension()
            );
            $request->thumbnail->storeAs('project/thumbnail/' . $filename);
            $project->thumbnail = $filename;
        }

        if ($request->inNav === "on") {
            $project->order = $request->order;
        }

        $project->name = $request->name;
        $project->route = $request->route;
        $project->description = $request->description;
        $project->permission = $request->permission;

        if ($request->inOverview === "on") {
            $project->inOverview = true;
        } else if ($request->inOverview === null) {
            $project->inOverview = false;
        }

        if ($request->inNav === "on") {
            $project->inNav = true;
        } else if ($request->inNav === null) {
            $project->inNav = false;
        }

        $project->save();

        return redirect(route('config.projects.overview'))->with("message", "Changes saved");
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
