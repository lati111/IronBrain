<?php

namespace App\Datatables\Auth;

use App\Datatables\AbstractDatatable;
use App\Models\Auth\Permission;
use App\Models\Config\Submenu;
use Illuminate\Http\Request;

class PermissionDatatable extends AbstractDatatable
{
    public function overviewData(Request $request)
    {
        $permissionCollection =
            $this->applyTableFilters($request, Permission::select())
                ->get();

        $tableData = [];
        foreach ($permissionCollection as $permission) {
            $actionHTML = sprintf(
                "<div class='flex flex-row gap-2'>%s %s</div>",
                $this->getModifyButton(
                    route('config.permission.modify', [$permission->id])
                ),
                $this->getDeleteButton(
                    $request,
                    route('config.permission.delete', [$permission->id]),
                ),
            );

            $tableData[] = [
                $permission->name,
                $permission->description,
                $permission->group,
                $actionHTML,
            ];
        }

        return response()->json($tableData, 200);
    }
}
