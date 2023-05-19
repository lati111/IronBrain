<?php

namespace App\Http\Controllers\Config;

use App\Enum\ErrorEnum;
use App\Enum\Config\ProjectEnum;
use App\Http\Controllers\Controller;
use App\Models\Config\Project;
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
            return redirect(route('config.projects.overview'))->with("error", ErrorEnum::INVALID_ROUTE_MESSAGE);
        }

        return view('config.projects.modify', array_merge($this->getBaseVariables(), [
            'project' => $project,
        ]));
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:nav__project,id',
            'thumbnail' => 'nullable|mimes:png,jpg,jpeg,svg,webp|max:240',
            'name' => 'required|string|max:64',
            'route' => 'required|string|max:255',
            'description' => 'required|string',
            'permission_id' => 'nullable|string|exists:auth__permission,id',
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
                ->with('error', ErrorEnum::INVALID_ROUTE_MESSAGE);
        }

        $project = null;
        if ($request->id !== null) {
            $project = Project::find($request->id);
        } else {
            $project = new Project();
            if ($request->thumbnail === null && $request->in_overview === "on") {
                return back()
                    ->withInput($request->all())
                    ->with('error', ProjectEnum::MISSING_THUMBNAIL_MESSAGE);
            }
        }

        if ($request->thumbnail !== null && $request->in_overview === "on") {
            $filename = sprintf(
                '%s.%s',
                $request->name,
                $request->thumbnail->extension()
            );
            $request->thumbnail->storeAs('project/thumbnail/' . $filename);
            $project->thumbnail = $filename;
        }

        if ($request->in_nav === "on") {
            $project->order = $request->order;
        }

        $project->name = $request->name;
        $project->route = $request->route;
        $project->description = $request->description;
        $project->permission_id = $request->permission_id;

        if ($request->in_overview === "on") {
            $project->in_overview = true;
        } else if ($request->in_overview === null) {
            $project->in_overview = false;
        }

        if ($request->in_nav === "on") {
            $project->in_nav = true;
        } else if ($request->in_nav === null) {
            $project->in_nav = false;
        }

        $project->save();

        return redirect(route('config.projects.overview'))->with("message", ProjectEnum::PROJECT_SAVED_MESSAGE);
    }

    public function delete(int $id)
    {
        $project = Project::find($id);
        if ($project !== null) {
            $project->delete();
            return redirect(route('config.projects.overview'))->with("message", ProjectEnum::PROJECT_DELETED_MESSAGE);
        } else {
            // todo custom error screen
            return redirect(route('config.projects.overview'))->with("error", ProjectEnum::PROJECT_NOT_FOUND_MESSAGE);
        }
    }
}
