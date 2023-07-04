<?php

namespace App\Dataproviders;

use Illuminate\Http\Request;

abstract class AbstractDatalist
{
    protected function getToken(Request $request): string {
        $request->session()->token();
        return csrf_token();
    }

    protected function applyTableFilters(Request $request, $builder) {
        return $builder
            ->offset(($request->get('page', 1) - 1) * $request->get('perpage', 10))
            ->take($request->get('perpage', 10));
    }
}
