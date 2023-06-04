<?php

namespace App\Http\Controllers\Config;

use App\Enum\Config\ProjectEnum;
use App\Enum\Config\SubmenuEnum;
use App\Enum\ErrorEnum;
use App\Http\Controllers\Controller;
use App\Models\Config\Project;
use App\Models\Config\Submenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class SubmenuController extends Controller
{
    public function new(int $project_id)
    {
        return view('config.projects.submenu.modify', array_merge($this->getBaseVariables(), [
            'project_id' => $project_id,
        ]));
    }

    public function modify(int $project_id, int $id)
    {
        $project = Project::find($project_id);
        if ($project === null) {
            // todo custom error screen
            return redirect(route('config.projects.overview'))->with("error", ProjectEnum::PROJECT_NOT_FOUND_MESSAGE);
        }

        $submenu = Submenu::find($id);
        if ($submenu === null) {
            // todo custom error screen
            return redirect(route('config.projects.modify', [$project->id]))->with("error", SubmenuEnum::SUBMENU_NOT_FOUND_MESSAGE);
        }

        return view('config.projects.submenu.modify', array_merge($this->getBaseVariables(), [
            'project_id' => $project_id,
            'submenu' => $submenu,
        ]));
    }

    public function save(Request $request, int $project_id)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|integer|exists:nav__submenu,id',
            'name' => 'required|string|max:64',
            'route' => 'required|string|max:255',
            'permission_id' => 'nullable|integer|exists:auth__permission,id',
            'order' => 'required|integer',
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

        $submenu = null;
        if ($request->id !== null) {
            $submenu = Submenu::find($request->id);
        } else {
            $submenu = new Submenu();
            $submenu->project_id = $project_id;
        }

        $submenu->name = $request->name;
        $submenu->route = $request->route;
        $submenu->order = $request->order;
        $submenu->permission_id = $request->permission_id;
        $submenu->save();

        return redirect(route('config.projects.modify', [$project_id]))->with('message', SubmenuEnum::SUBMENU_SAVED_MESSAGE);
    }

    public function delete(int $project_id, int $id) {
        $submenu = Submenu::find($id);
        if ($submenu !== null) {
            $submenu->delete();
            return redirect(route('config.projects.modify', $project_id))->with("message", SubmenuEnum::SUBMENU_DELETED_MESSAGE);
        } else {
            // todo custom error screen
            return redirect(route('config.projects.modify', $project_id))->with("error", SubmenuEnum::SUBMENU_NOT_FOUND_MESSAGE);
        }
    }
}
