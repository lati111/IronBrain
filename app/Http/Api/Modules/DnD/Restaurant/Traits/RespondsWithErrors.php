<?php

namespace App\Http\Api\Modules\DnD\Restaurant\Traits;

use App\Enum\ErrorEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * The error responses the restaurant's APIs share. Meant for classes extending AbstractApi, as it responds through it
 */
trait RespondsWithErrors
{
    /**
     * Validates the request parameters
     * @param Request $request The request parameters as given by Laravel
     * @param array $rules The validation rules
     * @return JsonResponse|null An error response listing what is wrong, or null when the request is valid
     */
    protected function validateRequest(Request $request, array $rules): ?JsonResponse
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        return null;
    }

    /**
     * Responds that something could not be found
     * @param string $what What could not be found, such as "Meal"
     * @return JsonResponse The error in JSON format
     */
    protected function notFound(string $what): JsonResponse
    {
        return $this->respond(Response::HTTP_NOT_FOUND, $what . ' not found');
    }
}
