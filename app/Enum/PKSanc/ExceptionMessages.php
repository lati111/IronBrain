<?php

namespace App\Enum\PKSanc;

class ExceptionMessages
{
    public const UNKNOWN_CSV_VERSION_PRIVATE = 'uploaded csv did not match any known formats';
    public const UNKNOWN_CSV_VERSION_PUBLIC = `The uploaded csv file did not match any known version, are you sure it was properly extracted through PKSaveExtract?`;
}
