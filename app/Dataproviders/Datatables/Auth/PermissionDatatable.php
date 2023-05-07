<?php

namespace App\Dataproviders\Datatables\Auth;

use App\Dataproviders\Datatables\AbstractDatatable;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
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
                $permission->permission,
                $permission->name,
                $permission->description,
                $permission->group,
                $actionHTML,
            ];
        }

        return response()->json($tableData, 200);
    }

    public function listToggleableData(Request $request, int $role_id)
    {
        $role = Role::find($role_id);
        if ($role === null) {
            return response()->json('Role not found', 404);
        }

        $permissionCollection =
            $this->applyTableFilters($request, Permission::select())
                ->get();

        $tableData = [];
        foreach ($permissionCollection as $permission) {
            $url = route('config.role.permission.toggle', [$role_id, $permission->id]);
            $checkbox = sprintf(
                "<input type='checkbox' onclick='togglePermission(this.checked, `%s`)' %s>",
                $url,
                $role->hasPermission($permission) ? 'checked' : ''
            );

            $tableData[] = [
                $checkbox,
                $permission->name,
                $permission->description,
            ];
        }

        return response()->json($tableData, 200);
    }
}
