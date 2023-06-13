<?php

namespace App\Dataproviders\Datatables\Config;

use App\Dataproviders\Datatables\AbstractDatatable;
use App\Models\Config\Project;
use App\Models\Config\Submenu;
use Illuminate\Http\Request;

class SubmenuDatatable extends AbstractDatatable
{
    public function overviewData(Request $request, int $project_id)
    {
        $project = Project::find($project_id);
        if ($project === null) {
            // todo custom error
            return redirect(route('config.projects.overview'))->with("error", "That project does not exist");
        }

        $submenuCollection =
            $this->applyTableFilters($request, Submenu::select())
                ->orderBy('order', 'asc')
                ->get();

        $tableData = [];
        foreach ($submenuCollection as $submenu) {
            $actionHTML = sprintf(
                "<div class='flex flex-row gap-2'>%s %s</div>",
                $this->getModifyButton(
                    route('config.projects.submenu.modify', [$project_id, $submenu->id]),
                    (string) $submenu->id
                ),
                $this->getDeleteButton(
                    $request,
                    route('config.projects.submenu.delete', [$project_id, $submenu->id]),
                    (string) $submenu->id
                ),
            );

            $tableData[] = [
                $submenu->name,
                $submenu->route,
                $submenu->order,
                $actionHTML,
            ];
        }

        return response()->json($tableData, 200);
    }
}
