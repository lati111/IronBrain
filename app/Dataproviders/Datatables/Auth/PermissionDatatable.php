<?php

namespace App\Dataproviders\Datatables\Auth;

use App\Dataproviders\Datatables\AbstractDatatable;
use App\Dataproviders\Traits\Paginatable;
use App\Enum\Auth\RoleEnum;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionDatatable extends AbstractDatatable
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
        $permissionCollection =
            $this->applyTableFilters($request, Permission::select())
                ->get();

        $tableData = [];
        foreach ($permissionCollection as $permission) {
            $actionHTML = sprintf(
                "<div class='flex flex-row gap-2'>%s %s</div>",
                $this->getModifyButton(
                    route('config.permission.modify', [$permission->id]),
                    $permission->id,
                ),
                $this->getDeleteButton(
                    $request,
                    route('config.permission.delete', [$permission->id]),
                    $permission->id
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
            return response()->json(RoleEnum::ROLE_NOT_FOUND_MESSAGE, 404);
        }

        $permissionCollection =
            $this->applyTableFilters($request, Permission::select())
                ->get();

        $tableData = [];
        foreach ($permissionCollection as $permission) {
            $toggle_url = route('config.role.permission.toggle', [$role_id, $permission->id]);
            $checkbox = sprintf(
                "<input type='checkbox' dusk='permission_checkbox_%s' onclick='togglePermission(this.checked, `%s`)' %s>",
                $permission->id,
                $toggle_url,
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
