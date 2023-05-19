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
    public function overviewDataTable(Request $request, int $project_id)
    {
        $token = $request->session()->token();
        $token = csrf_token();

        $project = Project::find($project_id);
        if ($project === null) {
            // todo custom error
            return redirect(route('config.projects.overview'))->with("error", ProjectEnum::PROJECT_NOT_FOUND_MESSAGE);
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

        $submenuCollection = Submenu::where('project_id', "=", $project_id)
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
                    route('config.projects.submenu.modify', [$project_id, $submenu->id]),
                    route('config.projects.submenu.delete', [$project_id, $submenu->id]),
                    $token
                ),
            ];
        }

        return response()->json($tableData, 200);
    }

    public function new(int $project_id)
    {
        return view('config.projects.submenu.modify', array_merge($this->getBaseVariables(), [
            'project_id' => $project_id,
        ]));
    }

    public function modify(int $project_id, int $id)
    {
        $submenu = Submenu::find($id);
        if ($submenu === null) {
            // todo custom error screen
            return redirect(route('config.projects.modify'), $project_id)->with("error", SubmenuEnum::SUBMENU_NOT_FOUND_MESSAGE);
        }

        return view('config.projects.submenu.modify', array_merge($this->getBaseVariables(), [
            'project_id' => $project_id,
            'submenu' => $submenu,
        ]));
    }

    public function save(Request $request, int $project_id)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'nullable|exists:nav__submenu,id',
            'name' => 'required|string|max:64',
            'route' => 'required|string|max:255',
            'permission_id' => 'nullable|string|exists:auth__permission,id',
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
        $submenu->permission_id = $request->permission_id;
        $submenu->order = $request->order;
        $submenu->save();

        return redirect(route('config.projects.modify', $project_id))->with("message", SubmenuEnum::SUBMENU_SAVED_MESSAGE);
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
