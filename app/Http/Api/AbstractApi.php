<?php

namespace App\Http\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class AbstractApi extends Controller
{
    public function respond(int $code, string $message, mixed $data = null): JsonResponse {
        $errors = null;
        if ($code < 200 || $code >= 300) {
            $errors = $data;
            $data = null;
        }

        return response()
            ->json([
                'code' => $code,
                'message' => $message,
                'data' => $data,
                'errors' => $errors
            ], $code);
    }
}
