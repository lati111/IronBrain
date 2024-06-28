<?php

namespace App\Http\Dataproviders\Traits;

use App\Enum\GenericStringEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait HasPages
{
    /**
     * Gets the amount of pages that exists with the given query parameters
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The amount of pages in JSON format
     */
    public function count(Request $request): JsonResponse
    {
        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $this->getPages($request));
    }
}
