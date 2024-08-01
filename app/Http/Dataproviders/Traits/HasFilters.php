<?php

namespace App\Http\Dataproviders\Traits;

use App\Enum\GenericStringEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait HasFilters
{
    // Method to be called from a route to get filter options
    public function filters(Request $request): JsonResponse
    {
        // Gets either a list of available filters, or a list of available options for a filter if one is specified
        $data = $this->getFilterData($request);

        // Return the data as a JsonResponse
        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }
}
