<?php

namespace App\Http\Dataproviders;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class AbstractCardlist extends AbstractDatalist
{
    /**
     * Returns the requested data for this cardlist
     * @return JsonResponse Returns a response in JSON format
     */
    abstract public function data(Request $request): JsonResponse;

    /**
     * Returns the total records matching the request
     * @return JsonResponse Returns a response in JSON format
     */
    abstract public function count(Request $request): JsonResponse;
}
