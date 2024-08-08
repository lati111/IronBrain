<?php

namespace App\Exceptions\Modules\PKSanc;

use App\Enum\PKSanc\ExceptionMessages;
use App\Exceptions\IronBrainException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Request;
use Throwable;

class ImportException extends IronBrainException
{
    public array $data;

    public function __construct(string $privateMessage, string $publicMessage, array $data, int $code) {
        $this->data = $data;
        parent::__construct($privateMessage, $publicMessage, $code);
    }

    public static function failedValidation(array $errors): Throwable {
        throw new self(
            ExceptionMessages::CSV_VALIDATION_FAILED_PRIVATE,
            ExceptionMessages::CSV_VALIDATION_FAILED_PUBLIC,
            $errors,
            400,
        );
    }

    public static function unknownCsvVersion(): Throwable {
        throw new self(
            ExceptionMessages::UNKNOWN_CSV_VERSION_PRIVATE,
            ExceptionMessages::UNKNOWN_CSV_VERSION_PUBLIC,
            [],
            400,
        );
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): Response
    {
        return response()->view('errors.modules.pksanc.import', ['errors' => $this->data]);
    }
}
