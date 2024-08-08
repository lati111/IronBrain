<?php

namespace Tests\Traits;

use App\Models\Administration\Klant;

trait HasMissableModels
{
    /**
     * Run the test in order to validate that a required model is missing
     * @param string $routeParameterName The name of the column containing the local key for uuid
     * @param string $model The model to fake a uuid for
     * @param string $notFoundMessage The message that should be expected
     * @return void
     */
    public function performMissableModelTest(string $routeParameterName, string $model, string $notFoundMessage): void {
        $item = $this->createRandomEntities($this->getModel()::factory(), 1)->first();
        $item[$routeParameterName] = $this->getFalseUuid($model);

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute($item), $this->getPostParameters(), $this->getDefaultHeaders());

        $response->assertNotFound();
        $this->assertEquals($notFoundMessage, $response->json()['message']);
    }
}
