<?php

namespace App\Enum\PKSanc;

class ExceptionMessages
{
    public const CSV_VALIDATION_FAILED_PRIVATE = 'Uploaded csv contained a validation error.';
    public const CSV_VALIDATION_FAILED_PUBLIC = 'The uploaded csv failed our validation checks.';

    public const UNKNOWN_CSV_VERSION_PRIVATE = 'uploaded csv did not match any known formats';
    public const UNKNOWN_CSV_VERSION_PUBLIC = 'The uploaded csv file did not match any known version, are you sure it was properly extracted through PKSaveExtract?';
}
