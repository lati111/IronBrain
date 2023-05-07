<?php

namespace App\Datatables;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class AbstractDatatable
{
    private const MODIFY_BUTTON_HTML =
        "<div class='text-center'>".
            "<a href='%s' class='interactive'>edit</a>".
        "</div>";

    private const DELETE_BUTTON_HTML =
        "<div class='text-center'>".
            "<form action='%s' method='POST'>".
                "<input type='hidden' name='_token' value='%s'/>".
                "<span ".
                    "onclick='store_form(this.closest(`form`)); openModal(`delete_modal`)' class='interactive'".
                    "/>delete</span>".
            "</form>".
        "</div>";

    protected function getToken(Request $request): string {
        $request->session()->token();
        return csrf_token();
    }

    protected function getModifyButton(string $route): string {
        return sprintf(
            self::MODIFY_BUTTON_HTML,
            $route,
        );
    }

    protected function getDeleteButton(Request $request, string $route): string {
        return sprintf(
            self::DELETE_BUTTON_HTML,
            $this->getToken($request),
            $route,
        );
    }

    protected function applyTableFilters(Request $request, $builder) {
        return $builder
            ->offset(($request->get('page', 1) - 1) * $request->get('perpage', 10))
            ->take($request->get('perpage', 10));
    }
}
