<?php

namespace Tests\Feature\API;

use App\Models\AbstractModel;
use App\Models\Auth\User;
use Illuminate\Testing\TestResponse;
use Tests\Feature\AbstractFeatureTester;

abstract class AbstractApiFeatureTester extends AbstractFeatureTester
{
    protected function getHttpClient(User|null $user): AbstractFeatureTester
    {
        $client = $this->withHeaders($this->getDefaultHeaders());

        if ($user !== null) {
            $client->actingAs($this->getOperationUser());
        }

        return $client;
    }

    /**
     * Get the type of operation this is. Can be GET, GET_ALL or POST
     * @return string The route
     */
    abstract protected function getOperationType(): string;

    /**
     * Get the user to perform the operation with
     * @return User|null The user
     */
    protected function getOperationUser(): User|null {
        return $this->getAdminUser();
    }

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

    /**
     * Asserts that the api message matches the specified message
     */
    protected function assertApiMessage(string $expected, TestResponse $response): void {
        $this->assertEquals($expected, $response->json('message'));
    }
}
