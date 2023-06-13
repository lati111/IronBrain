<?php

namespace App\Dataproviders\Datatables\Auth;

use App\Dataproviders\Datatables\AbstractDatatable;
use App\Models\Auth\Role;
use Illuminate\Http\Request;

class RoleDatatable extends AbstractDatatable
{
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
