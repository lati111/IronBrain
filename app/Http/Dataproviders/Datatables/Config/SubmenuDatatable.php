<?php

namespace App\Http\Dataproviders\Datatables\Config;

use App\Http\Dataproviders\Datatables\AbstractDatatable;
use App\Http\Dataproviders\Traits\Paginatable;
use App\Models\Config\Project;
use App\Models\Config\Submenu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmenuDatatable extends AbstractDatatable
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
