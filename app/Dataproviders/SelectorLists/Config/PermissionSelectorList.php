<?php

namespace App\Dataproviders\SelectorLists\Config;

use App\Models\Auth\Permission;

class PermissionSelectorList
{
    public function PermissionSelectorList()
    {
        $permissionCollection =
            Permission::select()
                ->get();

        $listData = [];
        foreach ($permissionCollection as $permission) {
            $listData[] = [
                'id' => $permission->id,
                'name' => $permission->name,
            ];
        }

        return response()->json($listData, 200);
    }
}
