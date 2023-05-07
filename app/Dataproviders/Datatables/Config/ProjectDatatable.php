<?php

namespace App\Dataproviders\Datatables\Config;

use App\Dataproviders\Datatables\AbstractDatatable;
use App\Models\Config\Project;
use Illuminate\Http\Request;

class ProjectDatatable extends AbstractDatatable
{
    public function overviewData(Request $request)
    {
        $projectCollection =
            $this->applyTableFilters($request, Project::select())
                ->get();

        $tableData = [];
        foreach ($projectCollection as $project) {
            $thumbnailHTML = null;
            if ($project->thumbnail !== null) {
                $thumbnailHTML = $this->getImageHtml (
                    asset('img/project/thumbnail/' . $project->thumbnail),
                    'thumbnail',
                );
            }

            $actionHTML = sprintf(
                "<div class='flex flex-row gap-2'>%s %s</div>",
                $this->getModifyButton(
                    route('config.role.modify', [$project->id])
                ),
                $this->getDeleteButton(
                    $request,
                    route('config.user.delete', [$project->id]),
                ),
            );

            $tableData[] = [
                $thumbnailHTML,
                $project->name,
                $project->description,
                $project->route,
                $project->order,
                $actionHTML,
            ];
        }

        return response()->json($tableData, 200);
    }
}

