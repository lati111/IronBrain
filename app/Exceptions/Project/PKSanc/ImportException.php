<?php

namespace App\Exceptions\Project\PKSanc;

use App\Enum\PKSanc\ExceptionMessages;
use App\Exceptions\IronBrainException;
use Throwable;

class ImportException extends IronBrainException
{
    public array $data;

    public function __construct(string $privateMessage, string $publicMessage, array $data, int $code) {
        $this->data = $data;
        parent::__construct($privateMessage, $publicMessage, $code);
    }

    public static function unknownCsvVersion(): Throwable {
        throw new self(
            ExceptionMessages::UNKNOWN_CSV_VERSION_PRIVATE,
            ExceptionMessages::UNKNOWN_CSV_VERSION_PUBLIC,
            [],
            400,
        );
    }
}
