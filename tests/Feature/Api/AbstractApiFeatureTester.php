<?php

namespace Tests\Feature\API;

use App\Models\AbstractModel;
use Tests\Feature\AbstractFeatureTester;

abstract class AbstractApiFeatureTester extends AbstractFeatureTester
{
    /**
     * Get the type of operation this is. Can be GET, GET_ALL or POST
     * @return string The route
     */
    abstract protected function getOperationType(): string;

    /**
     * Get the route used in the api test
     * @return string The route
     */
    abstract protected function getRoute(): string;

    /**
     * Get the core model used in this request
     * @return AbstractModel The model
     */
    abstract protected function getModel(): AbstractModel;

    /**
     * Get an array with default headers
     * @return array The headers
     */
    protected function getDefaultHeaders(): array {
        return [
            'Accept' => 'application/json'
        ];
    }
}
