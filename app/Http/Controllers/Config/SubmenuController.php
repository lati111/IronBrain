<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Submenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

class SubmenuController extends Controller
{
    public function overviewDataTable(Request $request, int $projectId)
    {
        $token = $request->session()->token();
        $token = csrf_token();

        $project = Project::find($projectId);
        if ($project === null) {
            // todo custom error
            return redirect(route('config.projects.overview'))->with("error", "That project does not exist");
        }

        $actionHTML =
        "<div class='flex flex-row gap-2'>".
            "<div class='text-center'>".
                "<a href='%s' class='interactive'>edit</a>".
            "</div>".
            "<div class='text-center'>".
                "<form action='%s' method='POST'>".
                    "<input type='hidden' name='_token' value='%s'/>".
                    "<span ".
                        "onclick='store_form(this.closest(`form`)); openModal(`delete_modal`)' class='interactive'".
                        "/>delete</span>".
                "</form>".
            "</div>".
        "</div>";

        $submenuCollection = Submenu::where('projectId', "=", $projectId)
            ->offset(($request->get('page', 1) - 1) * $request->get('perpage', 10))
            ->take($request->get('perpage', 10))
            ->get();

        $tableData = [];
        foreach ($submenuCollection as $submenu) {
            $tableData[] = [
                $submenu->name,
                $submenu->route,
                $submenu->order,
                sprintf(
                    $actionHTML,
                    route('config.projects.submenu.modify', [$projectId, $submenu->id]),
                    route('config.projects.submenu.delete', [$projectId, $submenu->id]),
                    $token
                ),
            ];
        }

        return response()->json($tableData, 200);
    }

    public function new(int $projectId)
    {
        return view('config.projects.submenu.modify', array_merge($this->getBaseVariables(), [
            'projectId' => $projectId,
        ]));
    }

    public function modify(int $projectId, int $id)
    {
        $submenu = Submenu::find($id);
        if ($submenu === null) {
            // todo custom error screen
            return redirect(route('config.projects.modify'), $projectId)->with("error", "That submenu does not exist");
        }

        return view('config.projects.submenu.modify', array_merge($this->getBaseVariables(), [
            'projectId' => $projectId,
            'submenu' => $submenu,
        ]));
    }

    public function save(Request $request, int $projectId)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:nav_submenu,id',
            'name' => 'required|string|max:64',
            'route' => 'required|string|max:255',
            'permission' => 'nullable|string"exists:permission,permission',
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
                ->with('error', "Route does not exist");
        }

        $submenu = null;
        if ($request->id !== null) {
            $submenu = Submenu::find($request->id);
        } else {
            $submenu = new Submenu();
            $submenu->projectId = $projectId;
        }

        $submenu->name = $request->name;
        $submenu->route = $request->route;
        $submenu->permission = $request->permission;
        $submenu->order = $request->order;
        $submenu->save();

        return redirect(route('config.projects.modify', $projectId))->with("message", "Changes saved");
    }

    public function delete(int $projectId, int $id) {
        $submenu = Submenu::find($id);
        if ($submenu !== null) {
            $submenu->delete();
            return redirect(route('config.projects.modify', $projectId))->with("message", "Submenu was deleted");
        } else {
            // todo custom error screen
            return redirect(route('config.projects.modify', $projectId))->with("error", "Invalid submenu");
        }
    }
}
