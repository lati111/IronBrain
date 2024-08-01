<?php

namespace Tests\Traits\Operations;

trait GetAllOperation
{
    /** @inheritDoc */
    protected function getOperationType(): string
    {
        return 'GET_ALL';
    }

    /**
     * Test that a get all operation is performed correctly
     * @return void
     */
    public function test_get_all(): void
    {
        $this->createRandomEntities($this->getModel()::factory(), 5);

        $response = $this->getHttpClient($this->getOperationUser())
            ->get($this->getRoute());

        $response->assertOk();
        $this->assertCount(5, $response->json()['data']);
    }
}
