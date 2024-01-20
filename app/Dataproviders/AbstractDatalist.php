<?php

namespace App\Dataproviders;

use App\Dataproviders\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

abstract class AbstractDatalist
{
    protected function getToken(Request $request): string {
        $request->session()->token();
        return csrf_token();
    }

    protected function applyTableFilters(Request $request, Builder|HasMany $builder, bool $pagination = true): Builder|HasMany|JsonResponse
    {
        return $builder;
    }
}
