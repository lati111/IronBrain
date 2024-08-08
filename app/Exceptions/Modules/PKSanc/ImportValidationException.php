<?php

namespace App\Exceptions\Modules\PKSanc;

use App\Enum\PKSanc\ExceptionMessages;
use App\Exceptions\IronBrainException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Throwable;

class ImportValidationException extends IronBrainException
{
    public array $data;

    public function __construct(array $data) {
        $this->data = $data;
        parent::__construct(
            ExceptionMessages::CSV_VALIDATION_FAILED_PRIVATE,
            ExceptionMessages::CSV_VALIDATION_FAILED_PUBLIC,
            ResponseAlias::HTTP_BAD_REQUEST
        );
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response
    {
        return response()->view('errors.modules.pksanc.import', array_merge(['data' => $this->data], Controller::getBaseVariables()));
    }
}
