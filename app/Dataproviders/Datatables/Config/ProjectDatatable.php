<?php

namespace App\Dataproviders\Datatables\Config;

use App\Dataproviders\Datatables\AbstractDatatable;
use App\Dataproviders\Traits\Paginatable;
use App\Models\Config\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectDatatable extends AbstractDatatable
{
    Use Paginatable;
    protected function applyTableFilters(Request $request, Builder|HasMany $builder, bool $pagination = true): Builder|HasMany|JsonResponse
    {
        $this->setPerPage(10);
        if ($pagination === true) {
            $builder = $this->applyPagination($request, $builder);
            if ($builder instanceof JsonResponse) {
                return $builder;
            }
        }

        return parent::applyTableFilters($request, $builder);
    }
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
                    route('config.projects.modify', [$project->id]),
                    (string) $project->id
                ),
                $this->getDeleteButton(
                    $request,
                    route('config.projects.delete', [$project->id]),
                    (string) $project->id
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

