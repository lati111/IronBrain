<?php

namespace App\Exceptions;

use Exception;

class IronBrainDataException extends IronBrainException
{
    private mixed $data;

    public function __construct(string $privateMessage, string $publicMessage, int $code, $data = null)
    {
        parent::__construct($privateMessage, $publicMessage, $code);
        $this->data = $data;
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}
