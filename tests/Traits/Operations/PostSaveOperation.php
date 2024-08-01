<?php

namespace Tests\Traits\Operations;

use App\Models\AbstractModel;

trait PostSaveOperation
{
    /** @inheritDoc */
    protected function getOperationType(): string
    {
        return 'POST_SAVE';
    }

    /**
     * Test that a get all operation is performed correctly
     * @return void
     */
    public function test_post_operation(): void
    {
        $params = $this->getPostParameters();

        $response = $this->getHttpClient($this->getOperationUser())
            ->post($this->getRoute(), $params, $this->getDefaultHeaders());

        $response->assertCreated();
        $this->assertArrayEquals($params, $response->json()['data']);
    }
}
