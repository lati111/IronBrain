<?php

namespace App\Http\Dataproviders\Datatables;

use App\Http\Dataproviders\AbstractDatalist;
use Illuminate\Http\Request;

abstract class AbstractDatatable extends AbstractDatalist
{
    private const MODIFY_BUTTON_HTML =
        "<div class='text-center'>".
            "<a href='%s' class='interactive' dusk='modify_%d'>edit</a>".
        "</div>";

    private const DELETE_BUTTON_HTML =
        "<div class='text-center'>".
            "<form action='%s' method='POST'>".
                "<input type='hidden' name='_token' value='%s'/>".
                "<span ".
                    "onclick='store_form(this.closest(`form`)); openModal(`delete_modal`)' ".
                    "class='interactive' dusk='delete_%s'".
                    "/>delete</span>".
            "</form>".
        "</div>";

    private const IMG_HTML =
        "<img ".
            "style='height: 48px'".
            "class='text-center'".
            "src='%s'".
            "alt='%s'".
        "/>";

    protected function getModifyButton(string $route, ?string $id = ""): string {
        return sprintf(
            self::MODIFY_BUTTON_HTML,
            $route,
            $id,
        );
    }

    protected function getDeleteButton(Request $request, string $route, ?string $id = ""): string {
        return sprintf(
            self::DELETE_BUTTON_HTML,
            $route,
            $this->getToken($request),
            $id
        );
    }

    protected function getImageHtml(string $src, string $alt): string {
        return sprintf(
            self::IMG_HTML,
            $src,
            $alt,
        );
    }
}
