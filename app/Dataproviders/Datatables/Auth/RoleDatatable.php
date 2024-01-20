<?php

namespace App\Dataproviders\Datatables\Auth;

use App\Dataproviders\Datatables\AbstractDatatable;
use App\Dataproviders\Traits\Paginatable;
use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleDatatable extends AbstractDatatable
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
        $roleCollection =
            $this->applyTableFilters($request, Role::select())
                ->get();

        $tableData = [];
        foreach ($roleCollection as $role) {
            $actionHTML = sprintf(
                "<div class='flex flex-row gap-2'>%s %s</div>",
                $this->getModifyButton(
                    route('config.role.modify', [$role->id]),
                    $role->id
                ),
                $this->getDeleteButton(
                    $request,
                    route('config.role.delete', [$role->id]),
                    $role->id
                ),
            );

            $tableData[] = [
                $role->name,
                $role->description,
                $actionHTML,
            ];
        }

        return response()->json($tableData, 200);
    }
}
