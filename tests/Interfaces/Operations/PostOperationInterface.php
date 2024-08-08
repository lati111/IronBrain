<?php

namespace Tests\Interfaces\Operations;

interface PostOperationInterface
{

    /**
     * Gets an array containing the parameters that should be passed for a valid operation
     * @param array $overwrites An array containing overwrites for the default parameter set
     * @return array The generated parameter set
     */
    function getPostParameters(array $overwrites = []): array;
}
