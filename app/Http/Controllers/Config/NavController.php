<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Submenu;
use Illuminate\Http\Request;

class NavController extends Controller
{
    public function getSubmenuCollection(Request $request, int $projectId)
    {
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
                    route('config.projects.submenu.delete', [$projectId, $submenu->id])
                ),
            ];
        }

        return response()->json($tableData, 200);
    }

    public function new(int $projectId)
    {
        return view('config.projects.modify', $this->getBaseVariables());
    }

    public function modify(int $projectId, int $id)
    {
    }

    public function save(Request $request, int $projectId)
    {
    }

    public function delete(int $projectId, int $id) {
    }
}
