<?php

namespace App\Http\Dataproviders\Datatables\Auth;

use App\Http\Dataproviders\Datatables\AbstractDatatable;
use App\Http\Dataproviders\Traits\Paginatable;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserDatatable extends AbstractDatatable
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

    private const CHANGE_ROLE_BUTTON_HTML =
        "<div class='text-center'>".
            "<form action='%s' method='POST'>".
                "<input type='hidden' name='_token' value='%s'/>".
                "<span ".
                    "onclick='load_select_modal(document.querySelector(`#role_modal`), %s); store_form(this.closest(`form`)); openModal(`role_modal`)'".
                    "class='interactive' dusk='change_role_%s'".
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
            $pfpHTML = $this->getImageHtml (
                asset('img/profile/' . $user->profile_picture),
                'profile picture',
            );

            $actionHTML = sprintf(
                "<div class='flex flex-col gap-2'>%s %s</div>",
                $this->getChangeRoleButton(
                    $request,
                    route('config.user.role.set', [$user->uuid]),
                    $user->uuid,
                    $user->role_id,
                ),
                $this->getDeleteButton(
                    $request,
                    route('config.user.delete', [$user->uuid]),
                    $user->uuid
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

    protected function getChangeRoleButton(Request $request, string $route, ?string $id, ?int $role_id): string {
        return sprintf(
            self::CHANGE_ROLE_BUTTON_HTML,
            $route,
            $this->getToken($request),
            $role_id,
            $id
        );
    }
}

