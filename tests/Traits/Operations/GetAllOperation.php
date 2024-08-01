<?php

namespace Tests\Traits\Operations;

use Database\Factories\Administration\Hours\HourFactory;

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

        $response = $this
            ->actingAs($this->getAdminUser())
            ->get($this->getRoute(), $this->getDefaultHeaders());

        $response->assertOk();
        $this->assertCount(5, $response->json()['data']);
    }
}
