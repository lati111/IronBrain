<?php

namespace App\Http\Dataproviders\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

trait Paginatable
{
    private int $perpage = 10;

    protected function applyPagination(Request $request, Builder|HasMany $builder): Builder|HasMany|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            "page" => "integer|nullable",
            "perpage" => "integer|nullable"
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), ResponseAlias::HTTP_BAD_REQUEST);
        }

        $page = $request->get('page', 1);
        $perpage = $request->get('perpage', $this->perpage);

        return $builder
            ->offset(($page - 1) * $perpage)
            ->take($perpage);
    }

    protected function getCount(Request $request, Builder|HasMany $builder, bool $pagination = true): float|int|JsonResponse
    {
        $count = $this->applyTableFilters($request, $builder, false);
        if ($count instanceof JsonResponse) {
            return $count;
        }

        $count = $count->count();

        if ($pagination === true) {
            $perpage = $request->get('perpage', $this->perpage);

            $pages = $count / $perpage;
            return ceil($pages);
        }

        return $count;
    }

    protected function setPerPage(int $perpage): void
    {
        $this->perpage = $perpage;
    }
}
