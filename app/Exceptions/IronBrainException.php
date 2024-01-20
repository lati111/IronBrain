<?php

namespace App\Exceptions;

use Exception;

class IronBrainException extends Exception
{
    public string $publicMessage;

    public string $privateMessage;

    public function __construct(string $privateMessage, string $publicMessage, int $code) {
        $this->privateMessage = $privateMessage;
        $this->publicMessage = $publicMessage;

        parent::__construct($privateMessage, $code, null);
    }
}
