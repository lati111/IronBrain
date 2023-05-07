<?php

namespace App\Datatables\Auth;

use App\Datatables\AbstractDatatable;
use App\Models\Auth\Permission;
use App\Models\Auth\User;
use Illuminate\Http\Request;

use function PHPUnit\Framework\isNull;

class UserDatatable extends AbstractDatatable
{
    private const IMG_HTML =
        "<img ".
            "style='height: 48px'".
            "class='text-center'".
            "src='%s'".
            "alt='profile picture'".
        "/>";

    private const CHANGE_ROLE_BUTTON_HTML =
        "<div class='text-center'>".
            "<form action='%s' method='POST'>".
                "<input type='hidden' name='_token' value='%s'/>".
                "<span ".
                    "onclick='load_select_modal(document.querySelector(`#role_modal`), %s); store_form(this.closest(`form`)); openModal(`role_modal`)' class='interactive'".
                    "/>change role</span>".
            "</form>".
        "</div>";

    public function overviewData(Request $request)
    {
        $userCollection =
            $this->applyTableFilters($request, User::select())
                ->get();

        $tableData = [];
        foreach ($userCollection as $user) {
            $pfpHTML = sprintf(
                self::IMG_HTML,
                asset('img/profile/' . $user->profile_picture)
            );

            $actionHTML = sprintf(
                "<div class='flex flex-col gap-2'>%s %s</div>",
                $this->getChangeRoleButton(
                    $request,
                    route('config.user.role.set', [$user->uuid]),
                    $user->role_id,
                ),
                $this->getDeleteButton(
                    $request,
                    route('config.user.delete', [$user->uuid]),
                ),
            );

            $role = $user->Role;
            $role = ($role !== null) ? $role->name : 'default';

            $tableData[] = [
                $pfpHTML,
                $user->name,
                $user->email,
                $role,
                $actionHTML,
            ];
        }

        return response()->json($tableData, 200);
    }

    protected function getChangeRoleButton(Request $request, string $route, ?int $role_id): string {
        return sprintf(
            self::CHANGE_ROLE_BUTTON_HTML,
            $route,
            $this->getToken($request),
            $role_id
        );
    }
}

