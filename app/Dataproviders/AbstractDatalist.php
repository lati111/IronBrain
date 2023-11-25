<?php

namespace App\Dataproviders;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

abstract class AbstractDatalist
{
    protected function getToken(Request $request): string {
        $request->session()->token();
        return csrf_token();
    }

    protected function applyTableFilters(Request $request, $builder, bool $pagination = true) {
        $builder = $this->applySearchbarFilters($request, $builder);

        if ($pagination === true) {
            $builder = $builder
            ->offset(($request->get('page', 1) - 1) * $request->get('perpage', 10))
            ->take($request->get('perpage', 10));
        }

        return $builder;
    }

    protected function getCount(Request $request, $builder, bool $pagination = true) {
        $count = $this->applyTableFilters($request, $builder, $pagination)->count();

        if ($pagination === true) {
            $perpage = $request->get('perpage', 10);

            $pages = $count / $perpage;
            if ($count % $perpage !== 0) {
                $pages++;
            }

            return $pages;
        }

        return $count;
    }

    private function applySearchbarFilters(Request $request, $builder) {
        $validator = Validator::make($request->all(), [
            "searchfields" => "string|required",
            "searchterm" => "string|required"
        ]);

        if ($validator->fails() === false) {
            $searchterm = $request->get("searchterm");
            $searchfields = explode(",", $request->get("searchfields"));
            $builder->where(function($query) use ($searchfields, $searchterm) {
                foreach($searchfields as $searchfield) {
                    $query->orWhere($searchfield, "LIKE", '%'.$searchterm.'%');
                }
            });
        }

        return $builder;
    }
}
